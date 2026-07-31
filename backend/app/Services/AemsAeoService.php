<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AuditEngagementOrderVersion;
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
            if ($locked->engagementOrder()->exists()) {
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
    ): AuditEngagementOrder {
        $permissions = [
            'SUBMIT' => 'aems.aeo.prepare',
            'RESUBMIT' => 'aems.aeo.prepare',
            'REVIEW' => 'aems.aeo.review',
            'RETURN' => 'aems.aeo.review',
            'APPROVE' => 'aems.aeo.approve',
            'ISSUE' => 'aems.aeo.issue',
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

        return DB::transaction(function () use ($request, $engagement, $order, $action, $lockVersion, $comment): AuditEngagementOrder {
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
    ): AuditEngagementOrder {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aeo.revise',
            $order->prepared_by,
        );

        return DB::transaction(function () use ($request, $engagement, $order, $lockVersion, $reason): AuditEngagementOrder {
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
            ]);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                'REVISE',
                $from,
                'DRAFT',
                ['versionNumber' => $source->version_number, 'status' => $from],
                ['versionNumber' => $number, 'status' => 'DRAFT'],
                $reason,
            );
            $this->support->audit(
                $request,
                'aems.aeo.revision_started',
                $engagement,
                ['versionNumber' => $source->version_number, 'status' => $from],
                ['versionNumber' => $number, 'status' => 'DRAFT'],
                ['aeoId' => $locked->id, 'aeoCode' => $locked->order_code, 'reason' => $reason],
            );

            return $locked->fresh();
        });
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

        return $errors;
    }

    private function nextStatus(AuditEngagementOrder $order, string $action): string
    {
        $transitions = [
            'DRAFT' => ['SUBMIT' => 'PENDING_REVIEW'],
            'PENDING_REVIEW' => ['REVIEW' => 'PENDING_REVIEW', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED'],
            'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED'],
            'RESUBMITTED' => ['REVIEW' => 'RESUBMITTED', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED'],
            'APPROVED' => ['ISSUE' => 'ISSUED'],
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
