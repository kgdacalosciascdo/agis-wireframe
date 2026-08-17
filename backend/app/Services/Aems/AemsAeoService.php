<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AuditEngagementOrderVersion;
use App\Models\AemsAeoDistribution;
use App\Models\AemsAeoSignatory;
use App\Models\EngagementEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Implements the controlled Audit Engagement Order lifecycle. Every content
 * change creates an immutable version, while EngagementEvent records preserve
 * review, return, approval, issue, and revision decisions.
 */
class AemsAeoService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
        private readonly AemsTeamSafeguardService $teamSafeguards,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'teamMembers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->with('user'),
            'engagementOrder.versions.creator',
            'engagementOrder.preparer',
            'engagementOrder.submitter',
            'engagementOrder.approver',
            'engagementOrder.issuer',
        ]);
        $order = $engagement->engagementOrder;

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'objectives' => $engagement->objectives,
                'scope' => $engagement->scope,
                'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
                'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
            ],
            'order' => $order ? $this->order($order, $engagement) : null,
            'teamReady' => $this->teamErrors($engagement) === [],
            'teamErrors' => $this->teamErrors($engagement),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): AuditEngagementOrder {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.prepare',
        );

        return DB::transaction(function () use ($request, $engagement, $attributes): AuditEngagementOrder {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->ensureEngagementMutable($locked);
            if ($locked->engagementOrder()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['order' => ['This engagement already has an active AEO.']]);
            }
            $order = AuditEngagementOrder::query()->create([
                'audit_engagement_id' => $locked->id,
                'order_code' => $this->orderCode($locked),
                'status' => 'DRAFT',
                'current_version_number' => 1,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $version = $this->version($request, $locked, $order, $attributes, 1);
            $this->seedSignatoryMatrix($order, $version, $request->user()->id);
            $this->event($request, $locked, $order, $version, 'CREATE', null, 'DRAFT', null, $this->versionSnapshot($version));
            $this->support->audit(
                $request,
                'aems.aeo.created',
                $locked,
                null,
                $this->versionSnapshot($version),
                ['aeoId' => $order->id, 'aeoCode' => $order->order_code],
            );

            return $order->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        array $attributes,
    ): AuditEngagementOrder {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.prepare',
        );

        return DB::transaction(function () use ($request, $engagement, $order, $attributes): AuditEngagementOrder {
            $locked = $this->lockOrder($engagement, $order, (int) $attributes['lockVersion']);
            if (! in_array($locked->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or returned AEO content can be revised.']]);
            }
            $previous = $locked->latestVersion()->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = $this->version($request, $engagement, $locked, $attributes, $number);
            $before = $this->versionSnapshot($previous);
            $after = $this->versionSnapshot($version);
            $locked->update([
                'current_version_number' => $number,
                'prepared_by' => $request->user()->id,
                'approved_by' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($request, $engagement, $locked, $version, 'UPDATE', $locked->status, $locked->status, $before, $after, $attributes['changeReason'] ?? null);
            $this->support->audit(
                $request,
                'aems.aeo.version_created',
                $engagement,
                $before,
                $after,
                ['aeoId' => $locked->id, 'aeoCode' => $locked->order_code],
            );

            return $locked->fresh();
        });
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        string $action,
        int $lockVersion,
        ?string $comment,
        string $signatureMethod = 'IN_APP_ATTESTATION',
        ?string $signatureReference = null,
    ): AuditEngagementOrder {
        $permissions = [
            'SUBMIT' => 'aems.aeo.prepare',
            'RESUBMIT' => 'aems.aeo.prepare',
            'REVIEW' => 'aems.aeo.review',
            'RETURN' => 'aems.aeo.review',
            'APPROVE' => 'aems.aeo.approve',
            'ISSUE' => 'aems.aeo.issue',
            'CANCEL' => 'aems.aeo.cancel',
            'VOID' => 'aems.aeo.void',
            'SUPERSEDE' => 'aems.aeo.supersede',
        ];
        if (! isset($permissions[$action])) {
            throw ValidationException::withMessages(['action' => ['Unsupported AEO workflow action.']]);
        }
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permissions[$action],
            in_array($action, ['REVIEW', 'RETURN', 'APPROVE', 'ISSUE'], true)
                ? $order->prepared_by : null,
        );

        return DB::transaction(function () use ($request, $engagement, $order, $action, $lockVersion, $comment, $signatureMethod, $signatureReference): AuditEngagementOrder {
            $locked = $this->lockOrder($engagement, $order, $lockVersion);
            $version = $locked->latestVersion()->firstOrFail();
            $from = $locked->status;
            $to = $this->nextStatus($locked, $action);
            if (in_array($action, ['RETURN'], true) && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages(['comment' => ['A clear return instruction is required.']]);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $errors = $this->teamErrors($engagement->fresh());
                if ($errors !== []) {
                    throw ValidationException::withMessages(['team' => $errors]);
                }
            }
            if ($action === 'APPROVE') {
                $reviewed = EngagementEvent::query()
                    ->where('audit_engagement_id', $engagement->id)
                    ->where('subject_type', 'AEO')
                    ->where('subject_id', $locked->id)
                    ->where('subject_version', $locked->current_version_number)
                    ->where('action', 'AEO_REVIEW')
                    ->where('actor_id', '<>', $locked->prepared_by)
                    ->exists();
                if (! $reviewed) {
                    throw ValidationException::withMessages([
                        'action' => ['The current AEO version must be independently reviewed before approval.'],
                    ]);
                }
            }
            if (in_array($action, ['CANCEL', 'VOID', 'SUPERSEDE'], true)
                && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages(['comment' => ['A clear authority reason is required.']]);
            }
            if ($action === 'ISSUE') {
                if (! $locked->approved_by) {
                    throw ValidationException::withMessages(['action' => ['Only an approved AEO may be issued.']]);
                }
                $this->ensureSignature($request, $engagement, $locked, $version, 'ISSUING_AUTHORITY', $signatureMethod, $signatureReference);
            } elseif ($action === 'APPROVE') {
                $this->ensureSignature($request, $engagement, $locked, $version, 'APPROVING_AUTHORITY', $signatureMethod, $signatureReference);
            } elseif ($action === 'REVIEW') {
                $this->ensureSignature($request, $engagement, $locked, $version, 'INDEPENDENT_REVIEWER', $signatureMethod, $signatureReference);
            }

            $changes = ['lock_version' => $locked->lock_version + 1];
            if ($action !== 'REVIEW') {
                $changes['status'] = $to;
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
            }
            if ($action === 'APPROVE') {
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
            }
            if ($action === 'ISSUE') {
                $changes['issued_by'] = $request->user()->id;
                $changes['issued_at'] = now();
            }
            if (in_array($action, ['CANCEL', 'VOID', 'SUPERSEDE'], true)) {
                $changes['is_active'] = false;
                if ($action === 'CANCEL') {
                    $changes['cancelled_by'] = $request->user()->id;
                    $changes['cancelled_at'] = now();
                } elseif ($action === 'VOID') {
                    $changes['voided_by'] = $request->user()->id;
                    $changes['voided_at'] = now();
                }
            }
            $locked->update($changes);
            $after = ['status' => $to, 'versionNumber' => $version->version_number];
            $this->event($request, $engagement, $locked, $version, $action, $from, $to, ['status' => $from], $after, $comment);
            $this->support->audit(
                $request,
                'aems.aeo.'.str($action)->lower(),
                $engagement,
                ['status' => $from],
                $after,
                ['aeoId' => $locked->id, 'aeoCode' => $locked->order_code, 'comment' => $comment],
            );
            $this->notifications->controlledDocumentTransition(
                $request,
                $engagement,
                'AEO',
                $locked->id,
                $locked->order_code,
                'Engagement Order',
                $action,
                $version->version_number,
                $locked->prepared_by,
                $locked->submitted_by,
                'aems.aeo.review',
                "/audit-engagement-management/aeo?engagementId={$engagement->id}",
            );

            return $locked->fresh();
        });
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        int $lockVersion,
        string $reason,
        string $action = 'REVISE',
    ): AuditEngagementOrder {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.revise',
            $order->prepared_by,
        );

        return DB::transaction(function () use ($request, $engagement, $order, $lockVersion, $reason, $action): AuditEngagementOrder {
            $locked = $this->lockOrder($engagement, $order, $lockVersion);
            if (! in_array($locked->status, ['APPROVED', 'ISSUED'], true)) {
                throw ValidationException::withMessages(['status' => ['Only an approved or issued AEO can start a formal revision.']]);
            }
            $source = $locked->latestVersion()->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = AuditEngagementOrderVersion::query()->create([
                ...$source->only([
                    'authority', 'objectives', 'scope', 'effectivity_date',
                    'planned_start_date', 'planned_end_date', 'team_snapshot',
                    'content_snapshot',
                ]),
                'audit_engagement_order_id' => $locked->id,
                'version_number' => $number,
                'change_reason' => $reason,
                'created_by' => $request->user()->id,
            ]);
            $this->seedSignatoryMatrix($locked, $version, $request->user()->id);
            $from = $locked->status;
            $locked->update([
                'status' => 'DRAFT',
                'current_version_number' => $number,
                'prepared_by' => $request->user()->id,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_at' => null,
                'lock_version' => $locked->lock_version + 1,
                'amended_from_version_number' => $source->version_number,
            ]);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                $action,
                $from,
                'DRAFT',
                ['versionNumber' => $source->version_number, 'status' => $from],
                ['versionNumber' => $number, 'status' => 'DRAFT'],
                $reason,
            );
            $this->support->audit(
                $request,
                $action === 'AMEND' ? 'aems.aeo.amendment_started' : 'aems.aeo.revision_started',
                $engagement,
                ['versionNumber' => $source->version_number, 'status' => $from],
                ['versionNumber' => $number, 'status' => 'DRAFT'],
                ['aeoId' => $locked->id, 'aeoCode' => $locked->order_code, 'reason' => $reason],
            );

            return $locked->fresh();
        });
    }

    public function amend(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        int $lockVersion,
        string $reason,
    ): AuditEngagementOrder {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.amend',
            $order->prepared_by,
        );

        return $this->revise($request, $engagement, $order, $lockVersion, $reason, 'AMEND');
    }

    /** @return array<string, mixed> */
    public function distributionWorkspace(AuditEngagement $engagement, AuditEngagementOrder $order): array
    {
        $this->ensureOrder($engagement, $order);
        return [
            'orderId' => $order->id,
            'orderCode' => $order->order_code,
            'versionNumber' => $order->current_version_number,
            'distributions' => $order->distributions()->with(['recipient', 'office', 'acknowledger'])
                ->where('version_number', $order->current_version_number)
                ->get()->map(fn (AemsAeoDistribution $distribution): array => $this->distribution($distribution))->values(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function distribute(Request $request, AuditEngagement $engagement, AuditEngagementOrder $order, array $attributes): AemsAeoDistribution
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.aeo.distribute');
        return DB::transaction(function () use ($request, $engagement, $order, $attributes): AemsAeoDistribution {
            $locked = $this->lockOrder($engagement, $order, (int) $attributes['lockVersion']);
            if ($locked->status !== 'ISSUED') {
                throw ValidationException::withMessages(['status' => ['Only an issued AEO may be distributed.']]);
            }
            $recipientType = $attributes['recipientType'];
            if ($recipientType === 'USER' && empty($attributes['recipientUserId'])) {
                throw ValidationException::withMessages(['recipientUserId' => ['A user recipient is required.']]);
            }
            if ($recipientType === 'OFFICE' && empty($attributes['recipientOfficeId'])) {
                throw ValidationException::withMessages(['recipientOfficeId' => ['An office recipient is required.']]);
            }
            $distribution = AemsAeoDistribution::query()->create([
                'audit_engagement_order_id' => $locked->id,
                'version_number' => $locked->current_version_number,
                'recipient_type' => $recipientType,
                'recipient_user_id' => $attributes['recipientUserId'] ?? null,
                'recipient_office_id' => $attributes['recipientOfficeId'] ?? null,
                'recipient_name' => $attributes['recipientName'] ?? null,
                'transmittal_method' => $attributes['transmittalMethod'],
                'transmittal_reference' => $attributes['transmittalReference'] ?? null,
                'status' => 'SENT',
                'sent_at' => now(),
                'created_by' => $request->user()->id,
            ]);
            $this->support->event($request, $engagement, 'AEO_DISTRIBUTED', $locked->status, $locked->status, null, ['distributionId' => $distribution->id, 'versionNumber' => $locked->current_version_number], $attributes['transmittalReference'] ?? null, 'AEO', $locked->id, $locked->current_version_number, $locked->order_code);
            $this->support->audit($request, 'aems.aeo.distributed', $engagement, null, $distribution->toArray(), ['aeoId' => $locked->id]);
            return $distribution->load(['recipient', 'office', 'acknowledger']);
        });
    }

    public function acknowledge(Request $request, AuditEngagement $engagement, AuditEngagementOrder $order, AemsAeoDistribution $distribution, string $note): AemsAeoDistribution
    {
        $this->ensureOrder($engagement, $order);
        if ((int) $distribution->audit_engagement_order_id !== (int) $order->id || $distribution->version_number !== $order->current_version_number) {
            throw ValidationException::withMessages(['distribution' => ['The distribution does not belong to the current AEO version.']]);
        }
        $isRecipient = ($distribution->recipient_user_id && (int) $distribution->recipient_user_id === (int) $request->user()->id)
            || ($distribution->recipient_office_id && (int) $distribution->recipient_office_id === (int) $request->user()->office_id);
        if (! $isRecipient) {
            $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.aeo.acknowledge');
        }
        if ($distribution->status === 'ACKNOWLEDGED') {
            throw ValidationException::withMessages(['distribution' => ['This transmittal is already acknowledged.']]);
        }
        $distribution->update(['status' => 'ACKNOWLEDGED', 'acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id, 'acknowledgement_note' => $note]);
        $this->support->event($request, $engagement, 'AEO_DISTRIBUTION_ACKNOWLEDGED', $order->status, $order->status, ['distributionId' => $distribution->id], ['status' => 'ACKNOWLEDGED', 'note' => $note], null, 'AEO', $order->id, $order->current_version_number, $order->order_code);
        $this->support->audit($request, 'aems.aeo.distribution_acknowledged', $engagement, null, $distribution->toArray(), ['aeoId' => $order->id]);
        return $distribution->fresh(['recipient', 'office', 'acknowledger']);
    }

    public function approvedVersion(AuditEngagement $engagement, AuditEngagementOrder $order): AuditEngagementOrderVersion
    {
        $this->ensureOrder($engagement, $order);
        $approvedVersion = EngagementEvent::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('subject_type', 'AEO')
            ->where('subject_id', $order->id)
            ->whereIn('action', ['AEO_APPROVE', 'AEO_ISSUE'])
            ->whereNotNull('subject_version')
            ->latest('created_at')
            ->value('subject_version');
        if (! $approvedVersion) {
            throw ValidationException::withMessages(['order' => ['No approved AEO version is available for PDF generation.']]);
        }

        return $order->versions()->where('version_number', $approvedVersion)->firstOrFail();
    }

    private function seedSignatoryMatrix(
        AuditEngagementOrder $order,
        AuditEngagementOrderVersion $version,
        int $createdBy,
    ): void {
        foreach ([
            ['role' => 'INDEPENDENT_REVIEWER', 'sequence' => 1],
            ['role' => 'APPROVING_AUTHORITY', 'sequence' => 2],
            ['role' => 'ISSUING_AUTHORITY', 'sequence' => 3],
        ] as $entry) {
            AemsAeoSignatory::query()->firstOrCreate(
                [
                    'audit_engagement_order_id' => $order->id,
                    'version_number' => $version->version_number,
                    'signatory_role' => $entry['role'],
                ],
                [
                    'sequence' => $entry['sequence'],
                    'is_required' => true,
                    'status' => 'PENDING',
                    'created_by' => $createdBy,
                ],
            );
        }
    }

    private function ensureSignature(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        AuditEngagementOrderVersion $version,
        string $role,
        string $method,
        ?string $reference,
    ): void {
        $entry = AemsAeoSignatory::query()->firstOrCreate(
            [
                'audit_engagement_order_id' => $order->id,
                'version_number' => $version->version_number,
                'signatory_role' => $role,
            ],
            [
                'sequence' => ['INDEPENDENT_REVIEWER' => 1, 'APPROVING_AUTHORITY' => 2, 'ISSUING_AUTHORITY' => 3][$role],
                'is_required' => true,
                'status' => 'PENDING',
                'created_by' => $request->user()->id,
            ],
        );
        if ($entry->status === 'SIGNED') {
            if ((int) $entry->signed_by !== (int) $request->user()->id) {
                throw ValidationException::withMessages(['signature' => ['A different authority already signed this AEO role.']]);
            }
            return;
        }
        if ($role === 'APPROVING_AUTHORITY') {
            $review = AemsAeoSignatory::query()
                ->where('audit_engagement_order_id', $order->id)
                ->where('version_number', $version->version_number)
                ->where('signatory_role', 'INDEPENDENT_REVIEWER')
                ->where('status', 'SIGNED')->first();
            if (! $review) {
                // Preserve compatibility with pre-G4 review events by materializing
                // the immutable signatory record from the already-audited action.
                $reviewEvent = EngagementEvent::query()
                    ->where('audit_engagement_id', $engagement->id)
                    ->where('subject_type', 'AEO')
                    ->where('subject_id', $order->id)
                    ->where('subject_version', $version->version_number)
                    ->where('action', 'AEO_REVIEW')
                    ->where('actor_id', '<>', $order->prepared_by)
                    ->latest('created_at')->first();
                if ($reviewEvent) {
                    $reviewEntry = AemsAeoSignatory::query()->firstOrCreate([
                        'audit_engagement_order_id' => $order->id,
                        'version_number' => $version->version_number,
                        'signatory_role' => 'INDEPENDENT_REVIEWER',
                    ], [
                        'sequence' => 1,
                        'is_required' => true,
                        'status' => 'PENDING',
                        'created_by' => $reviewEvent->actor_id,
                    ]);
                    $reviewEntry->fill([
                        'user_id' => $reviewEvent->actor_id,
                        'status' => 'SIGNED',
                        'signature_method' => 'LEGACY_EVENT_ATTESTATION',
                        'signature_reference' => "AEO-REVIEW-EVENT-{$reviewEvent->id}",
                        'signed_at' => $reviewEvent->created_at,
                        'signed_by' => $reviewEvent->actor_id,
                    ])->save();
                    $review = $reviewEntry->fresh();
                }
            }
            if (! $review || (int) $review->signed_by === (int) $request->user()->id) {
                throw ValidationException::withMessages(['signature' => ['An independent reviewer must sign before approval.']]);
            }
        }
        if ($role === 'ISSUING_AUTHORITY' && (int) $order->approved_by === (int) $request->user()->id) {
            throw ValidationException::withMessages(['signature' => ['The approving authority cannot also issue the same AEO.']]);
        }
        $entry->fill([
            'user_id' => $request->user()->id,
            'status' => 'SIGNED',
            'signature_method' => $method,
            'signature_reference' => $reference ?: "{$order->order_code}-v{$version->version_number}-{$role}-{$request->user()->id}",
            'signed_at' => now(),
            'signed_by' => $request->user()->id,
            'remarks' => null,
        ])->save();
        $this->support->audit($request, 'aems.aeo.signature_recorded', $engagement, null, [
            'aeoId' => $order->id,
            'versionNumber' => $version->version_number,
            'role' => $role,
            'signatureMethod' => $method,
        ]);
    }

    /** @return array<string, mixed> */
    private function distribution(AemsAeoDistribution $distribution): array
    {
        return [
            'id' => $distribution->id,
            'versionNumber' => $distribution->version_number,
            'recipientType' => $distribution->recipient_type,
            'recipientName' => $distribution->recipient_name,
            'recipientUser' => $this->user($distribution->recipient),
            'office' => $distribution->office ? [
                'id' => $distribution->office->id,
                'code' => $distribution->office->code,
                'name' => $distribution->office->name,
            ] : null,
            'transmittalMethod' => $distribution->transmittal_method,
            'transmittalReference' => $distribution->transmittal_reference,
            'status' => $distribution->status,
            'sentAt' => $distribution->sent_at?->toISOString(),
            'acknowledgedAt' => $distribution->acknowledged_at?->toISOString(),
            'acknowledgedBy' => $this->user($distribution->acknowledger),
            'acknowledgementNote' => $distribution->acknowledgement_note,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function version(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        array $attributes,
        int $number,
    ): AuditEngagementOrderVersion {
        $engagement->loadMissing([
            'teamMembers' => fn ($query) => $query
                ->where('is_active', true)->whereNull('ended_at')->with('user'),
            'offices:id,code,name',
            'auditAreas:id,code,name',
        ]);
        $team = $engagement->teamMembers->map(fn ($member): array => [
            'assignmentId' => $member->id,
            'role' => $member->assignment_role_code,
            'plannedPersonDays' => (float) $member->planned_person_days,
            'assignedFrom' => $member->assigned_from?->toDateString(),
            'assignedUntil' => $member->assigned_until?->toDateString(),
            'user' => [
                'id' => $member->user?->id,
                'employeeId' => $member->user?->employee_id,
                'name' => $member->user?->name,
                'position' => $member->user?->position,
            ],
        ])->values()->all();
        $content = [
            'capturedAt' => now()->toISOString(),
            'engagement' => [
                'id' => $engagement->id,
                'code' => $engagement->engagement_code,
                'title' => $engagement->title,
                'sourceType' => $engagement->source_type,
            ],
            'offices' => $engagement->offices->map->only(['id', 'code', 'name'])->values()->all(),
            'auditAreas' => $engagement->auditAreas->map->only(['id', 'code', 'name'])->values()->all(),
        ];

        return AuditEngagementOrderVersion::query()->create([
            'audit_engagement_order_id' => $order->id,
            'version_number' => $number,
            'authority' => $attributes['authority'],
            'objectives' => $attributes['objectives'],
            'scope' => $attributes['scope'],
            'effectivity_date' => $attributes['effectivityDate'] ?? null,
            'planned_start_date' => $attributes['plannedStartDate'] ?? null,
            'planned_end_date' => $attributes['plannedEndDate'] ?? null,
            'team_snapshot' => $team,
            'content_snapshot' => $content,
            'change_reason' => $attributes['changeReason'] ?? null,
            'created_by' => $request->user()->id,
        ]);
    }

    private function lockOrder(
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        int $lockVersion,
    ): AuditEngagementOrder {
        $locked = AuditEngagementOrder::query()->lockForUpdate()->findOrFail($order->id);
        $this->ensureOrder($engagement, $locked);
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This AEO changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureOrder(AuditEngagement $engagement, AuditEngagementOrder $order): void
    {
        if ((int) $order->audit_engagement_id !== (int) $engagement->id || $order->trashed()) {
            throw ValidationException::withMessages(['order' => ['The AEO does not belong to this engagement.']]);
        }
    }

    private function ensureEngagementMutable(AuditEngagement $engagement): void
    {
        if ($engagement->trashed() || in_array($engagement->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['engagement' => ['An AEO cannot be prepared for this engagement.']]);
        }
    }

    /** @return list<string> */
    private function teamErrors(AuditEngagement $engagement): array
    {
        $roles = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('assignment_role_code');
        $errors = [];
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $role) {
            if (! $roles->contains($role)) {
                $errors[] = str($role)->replace('_', ' ')->title().' is required before AEO submission.';
            }
        }

        foreach ($this->teamSafeguards->aggregateGate($engagement) as $gate) {
            $errors[] = $gate['label'].' before AEO approval.';
        }

        return $errors;
    }

    private function nextStatus(AuditEngagementOrder $order, string $action): string
    {
        $transitions = [
            'DRAFT' => ['SUBMIT' => 'PENDING_REVIEW', 'CANCEL' => 'CANCELLED'],
            'PENDING_REVIEW' => ['REVIEW' => 'PENDING_REVIEW', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED', 'CANCEL' => 'CANCELLED'],
            'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED', 'CANCEL' => 'CANCELLED'],
            'RESUBMITTED' => ['REVIEW' => 'RESUBMITTED', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED', 'CANCEL' => 'CANCELLED'],
            'APPROVED' => ['ISSUE' => 'ISSUED', 'CANCEL' => 'CANCELLED', 'VOID' => 'VOIDED', 'SUPERSEDE' => 'SUPERSEDED'],
            'ISSUED' => ['CANCEL' => 'CANCELLED', 'VOID' => 'VOIDED', 'SUPERSEDE' => 'SUPERSEDED'],
        ];
        $next = $transitions[$order->status][$action] ?? null;
        if (! $next) {
            throw ValidationException::withMessages([
                'action' => ["{$action} is not allowed while the AEO is {$order->status}."],
            ]);
        }

        return $next;
    }

    private function orderCode(AuditEngagement $engagement): string
    {
        $base = 'AEO-'.$engagement->engagement_code;
        if (! AuditEngagementOrder::withTrashed()->where('order_code', $base)->exists()) {
            return $base;
        }

        return $base.'-'.Str::upper(Str::random(4));
    }

    /** @return array<string, mixed> */
    private function order(AuditEngagementOrder $order, AuditEngagement $engagement): array
    {
        $events = EngagementEvent::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('subject_type', 'AEO')
            ->where('subject_id', $order->id)
            ->with('actor')
            ->orderByDesc('created_at')
            ->get();

        return [
            'id' => $order->id,
            'orderCode' => $order->order_code,
            'status' => $order->status,
            'currentVersionNumber' => $order->current_version_number,
            'lockVersion' => $order->lock_version,
            'preparedBy' => $this->user($order->preparer),
            'submittedBy' => $this->user($order->submitter),
            'submittedAt' => $order->submitted_at?->toISOString(),
            'approvedBy' => $this->user($order->approver),
            'approvedAt' => $order->approved_at?->toISOString(),
            'issuedBy' => $this->user($order->issuer),
            'issuedAt' => $order->issued_at?->toISOString(),
            'cancelledBy' => $this->user($order->canceller),
            'cancelledAt' => $order->cancelled_at?->toISOString(),
            'voidedBy' => $this->user($order->voider),
            'voidedAt' => $order->voided_at?->toISOString(),
            'amendedFromVersionNumber' => $order->amended_from_version_number,
            'latestVersion' => $order->latestVersion
                ? $this->versionSnapshot($order->latestVersion) : null,
            'versions' => $order->versions->sortByDesc('version_number')
                ->map(fn (AuditEngagementOrderVersion $version): array => $this->versionSnapshot($version))
                ->values(),
            'events' => $events->map(fn (EngagementEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'subjectVersion' => $event->subject_version,
                'comment' => $event->comment,
                'createdAt' => $event->created_at?->toISOString(),
                'actor' => $this->user($event->actor),
            ])->values(),
            'approvedPdfAvailable' => $events->contains(fn ($event): bool => in_array($event->action, ['AEO_APPROVE', 'AEO_ISSUE'], true)),
            'signatories' => $order->signatories->where('version_number', $order->current_version_number)
                ->map(fn (AemsAeoSignatory $entry): array => [
                    'id' => $entry->id,
                    'role' => $entry->signatory_role,
                    'status' => $entry->status,
                    'required' => (bool) $entry->is_required,
                    'method' => $entry->signature_method,
                    'reference' => $entry->signature_reference,
                    'signedAt' => $entry->signed_at?->toISOString(),
                    'user' => $this->user($entry->user),
                    'signer' => $this->user($entry->signer),
                ])->values(),
            'distributions' => $order->distributions->where('version_number', $order->current_version_number)
                ->map(fn (AemsAeoDistribution $distribution): array => $this->distribution($distribution))->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionSnapshot(AuditEngagementOrderVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'authority' => $version->authority,
            'objectives' => $version->objectives,
            'scope' => $version->scope,
            'effectivityDate' => $version->effectivity_date?->toDateString(),
            'plannedStartDate' => $version->planned_start_date?->toDateString(),
            'plannedEndDate' => $version->planned_end_date?->toDateString(),
            'teamSnapshot' => $version->team_snapshot ?? [],
            'contentSnapshot' => $version->content_snapshot ?? [],
            'changeReason' => $version->change_reason,
            'createdBy' => $this->user($version->creator),
            'createdAt' => $version->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function event(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        AuditEngagementOrderVersion $version,
        string $action,
        ?string $from,
        ?string $to,
        ?array $oldValues,
        ?array $newValues,
        ?string $comment = null,
    ): void {
        $this->support->event(
            $request,
            $engagement,
            'AEO_'.$action,
            $from,
            $to,
            $oldValues,
            $newValues,
            $comment,
            'AEO',
            $order->id,
            $version->version_number,
            $order->order_code,
        );
    }
}
