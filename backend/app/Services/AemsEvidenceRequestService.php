<?php

namespace App\Services;

use App\Models\AemsEvidenceAssessment;
use App\Models\AemsEvidenceRequest;
use App\Models\AemsEvidenceRequestEvidence;
use App\Models\AemsEvidenceRequestVersion;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\DocumentVersion;
use App\Models\EngagementEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Governs evidence requests, exact received-document links, and assessments. */
class AemsEvidenceRequestService
{
    private const ASSESSMENT_DIMENSIONS = [
        'sufficiency', 'appropriateness', 'relevance', 'reliability',
        'competence', 'accuracy', 'completeness', 'corroboration',
        'contradiction', 'authenticity', 'integrity',
    ];

    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.view');
        $requests = AemsEvidenceRequest::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->with($this->requestRelations())
            ->orderBy('request_code')
            ->get();
        $assessments = AemsEvidenceAssessment::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->with(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover'])
            ->orderByDesc('id')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'requestStatuses' => AemsEvidenceRequest::STATUSES,
            'assessmentStatuses' => AemsEvidenceAssessment::STATUSES,
            'requests' => $requests->map(fn (AemsEvidenceRequest $item): array => $this->requestData($item))->values(),
            'assessments' => $assessments->map(fn (AemsEvidenceAssessment $item): array => $this->assessmentData($item))->values(),
            'evidence' => AuditEvidence::query()
                ->visibleTo($request->user())
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->with('currentAssessment')
                ->orderBy('evidence_code')
                ->get()
                ->map(fn (AuditEvidence $item): array => $this->evidenceSummary($item))
                ->values(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, AuditEngagement $engagement, array $attributes): AemsEvidenceRequest
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.create');
        $record = DB::transaction(function () use ($request, $engagement, $attributes): AemsEvidenceRequest {
            $this->assertOfficeAndUser($engagement, $attributes);
            $record = AemsEvidenceRequest::query()->create([
                'request_family_uuid' => (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'request_code' => $this->nextCode($engagement),
                ...$this->requestAttributes($attributes),
                'status' => 'DRAFT',
                'current_version_number' => 1,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $this->createVersion($request, $record, $attributes, 1, null);
            $this->event($request, $engagement, $record, 'CREATED', null, 'DRAFT', null);
            $this->support->audit($request, 'aems.evidence-request.created', $engagement, null, $this->auditValues($record), ['evidenceRequestId' => $record->id]);
            return $record;
        }, 3);
        return $this->loadRequest($record);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, array $attributes): AemsEvidenceRequest
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.update');
        $record = DB::transaction(function () use ($request, $engagement, $record, $attributes): AemsEvidenceRequest {
            $locked = $this->lockRequest($engagement, $record, (int) $attributes['lockVersion']);
            if ($locked->status !== 'DRAFT') {
                throw ValidationException::withMessages(['status' => ['Only draft Evidence Requests can be edited.']]);
            }
            $this->assertOfficeAndUser($engagement, $attributes);
            $number = $locked->current_version_number + 1;
            $before = $this->auditValues($locked);
            $this->createVersion($request, $locked, $attributes, $number, $attributes['changeReason'] ?? null);
            $locked->update([
                ...$this->requestAttributes($attributes),
                'current_version_number' => $number,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($request, $engagement, $locked, 'VERSION_CREATED', 'DRAFT', 'DRAFT', $attributes['changeReason'] ?? null);
            $this->support->audit($request, 'aems.evidence-request.version_created', $engagement, $before, $this->auditValues($locked), ['evidenceRequestId' => $locked->id, 'changeReason' => $attributes['changeReason'] ?? null]);
            return $locked;
        }, 3);
        return $this->loadRequest($record);
    }

    public function transition(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, string $action, int $lockVersion, ?string $comment): AemsEvidenceRequest
    {
        $permission = match ($action) {
            'SUBMIT' => 'aems.evidence-request.submit',
            'SEND' => 'aems.evidence-request.send',
            'MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED' => 'aems.evidence-request.receive',
            'ASSESS' => 'aems.evidence-request.assess',
            'CLOSE' => 'aems.evidence-request.close',
            default => throw ValidationException::withMessages(['action' => ['Unsupported Evidence Request action.']]),
        };
        $this->access->authorizeEngagementAction($request->user(), $engagement, $permission, $record->prepared_by);
        $record = DB::transaction(function () use ($request, $engagement, $record, $action, $lockVersion, $comment): AemsEvidenceRequest {
            $locked = $this->lockRequest($engagement, $record, $lockVersion);
            $from = $locked->status;
            $to = match ($action) {
                'SUBMIT' => $from === 'DRAFT' ? 'SUBMITTED' : null,
                'SEND' => $from === 'SUBMITTED' ? 'SENT' : null,
                'MARK_PARTIALLY_RECEIVED' => in_array($from, ['SENT', 'PARTIALLY_RECEIVED'], true) ? 'PARTIALLY_RECEIVED' : null,
                'MARK_RECEIVED' => in_array($from, ['SENT', 'PARTIALLY_RECEIVED'], true) ? 'RECEIVED' : null,
                'ASSESS' => $from === 'RECEIVED' ? 'ASSESSED' : null,
                'CLOSE' => $from === 'ASSESSED' ? 'CLOSED' : null,
            };
            if (! $to) {
                throw ValidationException::withMessages(['status' => ["{$action} is not available while the request is {$from}."]]);
            }
            if (in_array($action, ['MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED', 'ASSESS'], true)
                && $locked->evidenceLinks->isEmpty()) {
                throw ValidationException::withMessages(['evidence' => ['Link at least one exact Evidence/Core Document Version first.']]);
            }
            if ($action === 'ASSESS') {
                $this->ensureRequestEvidenceAssessed($locked);
            }
            if ($action === 'CLOSE' && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages(['comment' => ['A closure reason is required.']]);
            }
            $changes = ['status' => $to, 'lock_version' => $locked->lock_version + 1];
            if ($action === 'SUBMIT') { $changes += ['submitted_by' => $request->user()->id, 'submitted_at' => now()]; }
            if ($action === 'SEND') { $changes += ['sent_by' => $request->user()->id, 'sent_at' => now()]; }
            if ($action === 'MARK_PARTIALLY_RECEIVED') { $changes['partially_received_at'] = now(); }
            if ($action === 'MARK_RECEIVED') { $changes['received_at'] = now(); }
            if ($action === 'ASSESS') { $changes['assessed_at'] = now(); }
            if ($action === 'CLOSE') { $changes += ['closed_at' => now(), 'closed_by' => $request->user()->id, 'closure_reason' => $comment]; }
            $locked->update($changes);
            $this->event($request, $engagement, $locked, $action, $from, $to, $comment);
            $this->support->audit($request, 'aems.evidence-request.'.str($action)->lower(), $engagement, ['status' => $from], $this->auditValues($locked), ['evidenceRequestId' => $locked->id, 'comment' => $comment]);
            $this->notifications->evidenceRequestTransition($request, $engagement, $locked, $action);
            return $locked;
        }, 3);
        return $this->loadRequest($record);
    }

    /** @param array<string, mixed> $attributes */
    public function receiveEvidence(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, array $attributes): AemsEvidenceRequestEvidence
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.receive', $record->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $record, $attributes): AemsEvidenceRequestEvidence {
            $locked = $this->lockRequest($engagement, $record, (int) $attributes['lockVersion']);
            if (! in_array($locked->status, ['SENT', 'PARTIALLY_RECEIVED'], true)) {
                throw ValidationException::withMessages(['status' => ['Evidence can only be received after the request is sent.']]);
            }
            $evidence = AuditEvidence::query()->where('audit_engagement_id', $engagement->id)->whereKey((int) $attributes['evidenceId'])->whereNot('status', 'VOIDED')->with('documentVersion')->first();
            if (! $evidence) throw ValidationException::withMessages(['evidenceId' => ['Evidence does not belong to this engagement or is voided.']]);
            if ((int) $evidence->document_version_id !== (int) $attributes['documentVersionId']) throw ValidationException::withMessages(['documentVersionId' => ['The request link must pin the Evidence row to its exact Core Document Version.']]);
            $version = DocumentVersion::query()->whereKey((int) $attributes['documentVersionId'])->where('document_id', $evidence->documentVersion->document_id)->first();
            if (! $version) throw ValidationException::withMessages(['documentVersionId' => ['The Core Document Version is not part of this Evidence record.']]);
            $link = AemsEvidenceRequestEvidence::query()->firstOrCreate(
                ['evidence_request_id' => $locked->id, 'audit_evidence_id' => $evidence->id, 'document_version_id' => $version->id],
                ['received_by' => $request->user()->id, 'received_at' => now(), 'receipt_notes' => $attributes['receiptNotes'] ?? null],
            );
            $locked->increment('lock_version');
            $this->event($request, $engagement, $locked, 'EVIDENCE_LINKED', $locked->status, $locked->status, $attributes['receiptNotes'] ?? null, [$version->id]);
            $this->support->audit($request, 'aems.evidence-request.evidence_linked', $engagement, null, $this->auditValues($locked), ['evidenceRequestId' => $locked->id, 'evidenceId' => $evidence->id, 'documentVersionId' => $version->id]);
            return $link->fresh(['evidence', 'documentVersion', 'receiver']);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function assessEvidence(Request $request, AuditEngagement $engagement, array $attributes): AemsEvidenceAssessment
    {
        $assessment = DB::transaction(function () use ($request, $engagement, $attributes): AemsEvidenceAssessment {
            $evidence = AuditEvidence::query()->where('audit_engagement_id', $engagement->id)->whereKey((int) $attributes['evidenceId'])->whereNot('status', 'VOIDED')->with('documentVersion')->first();
            if (! $evidence) throw ValidationException::withMessages(['evidenceId' => ['Evidence does not belong to this engagement or is voided.']]);
            if (! in_array($evidence->status, ['VERIFIED', 'LOCKED'], true)) throw ValidationException::withMessages(['evidenceId' => ['Only verified or locked Evidence can be professionally assessed.']]);
            $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence.assess', $evidence->uploaded_by);
            if ((int) $evidence->document_version_id !== (int) $attributes['documentVersionId']) throw ValidationException::withMessages(['documentVersionId' => ['Assessment must cite the exact current Core Document Version.']]);
            $requestRecord = null;
            if (! empty($attributes['evidenceRequestId'])) {
                $requestRecord = AemsEvidenceRequest::query()->where('audit_engagement_id', $engagement->id)->find((int) $attributes['evidenceRequestId']);
                if (! $requestRecord) throw ValidationException::withMessages(['evidenceRequestId' => ['Evidence Request does not belong to this engagement.']]);
                $linked = AemsEvidenceRequestEvidence::query()
                    ->where('evidence_request_id', $requestRecord->id)
                    ->where('audit_evidence_id', $evidence->id)
                    ->where('document_version_id', $evidence->document_version_id)
                    ->exists();
                if (! $linked) throw ValidationException::withMessages(['evidenceRequestId' => ['The assessment request must already contain the exact received Evidence/Core Document Version.']]);
            }
            if (($attributes['exceptionRequired'] ?? false) && blank($attributes['exceptionReason'] ?? null)) throw ValidationException::withMessages(['exceptionReason' => ['An exception reason is required when exception approval is requested.']]);
            $current = $evidence->currentAssessment()->first();
            $number = $current ? $current->version_number + 1 : 1;
            if ($current) {
                if (empty($attributes['changeReason'])) throw ValidationException::withMessages(['changeReason' => ['A correction reason is required for a new assessment version.']]);
                $this->supersedeAssessment($current);
            }
            $assessment = AemsEvidenceAssessment::query()->create([
                'assessment_family_uuid' => $current?->assessment_family_uuid ?? (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'audit_evidence_id' => $evidence->id,
                'evidence_request_id' => $requestRecord?->id,
                'document_version_id' => $evidence->document_version_id,
                'version_number' => $number,
                'supersedes_assessment_id' => $current?->id,
                'is_current_revision' => true,
                'status' => 'ASSESSED',
                ...$this->assessmentAttributes($attributes),
                'assessed_by' => $request->user()->id,
                'assessed_at' => now(),
                'change_reason' => $attributes['changeReason'] ?? null,
                'lock_version' => 1,
            ]);
            $this->support->event($request, $engagement, 'EVIDENCE_ASSESSED', null, 'ASSESSED', $current ? ['versionNumber' => $current->version_number] : null, $this->assessmentValues($assessment), $attributes['changeReason'] ?? null, 'AEMS_EVIDENCE_ASSESSMENT', $assessment->id, $number, $evidence->evidence_code, $assessment->assessment_family_uuid, [$assessment->document_version_id]);
            $this->support->audit($request, 'aems.evidence.assessed', $engagement, null, $this->assessmentValues($assessment), ['evidenceId' => $evidence->id, 'assessmentId' => $assessment->id]);
            $this->notifications->evidenceAssessmentRecorded($request, $engagement, $assessment);
            return $assessment;
        }, 3);
        return $assessment->fresh(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
    }

    public function approveException(Request $request, AuditEngagement $engagement, AemsEvidenceAssessment $assessment, int $lockVersion, string $comment): AemsEvidenceAssessment
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence.exception_approve', $assessment->assessed_by);
        $updated = DB::transaction(function () use ($request, $engagement, $assessment, $lockVersion, $comment): AemsEvidenceAssessment {
            $locked = AemsEvidenceAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ((int) $locked->audit_engagement_id !== (int) $engagement->id || $locked->lock_version !== $lockVersion) throw ValidationException::withMessages(['lockVersion' => ['This assessment changed in another session. Refresh before continuing.']]);
            if (! $locked->is_current_revision || ! $locked->exception_required) throw ValidationException::withMessages(['assessment' => ['Only a current assessment requiring an exception can be approved.']]);
            if (mb_strlen(trim($comment)) < 5) throw ValidationException::withMessages(['comment' => ['A clear exception approval explanation is required.']]);
            $this->supersedeAssessment($locked);
            $revision = AemsEvidenceAssessment::query()->create([
                'assessment_family_uuid' => $locked->assessment_family_uuid,
                'audit_engagement_id' => $locked->audit_engagement_id,
                'audit_evidence_id' => $locked->audit_evidence_id,
                'evidence_request_id' => $locked->evidence_request_id,
                'document_version_id' => $locked->document_version_id,
                'version_number' => $locked->version_number + 1,
                'supersedes_assessment_id' => $locked->id,
                'is_current_revision' => true,
                'status' => 'ASSESSED',
                ...$this->assessmentSnapshotAttributes($locked),
                'exception_approved_by' => $request->user()->id,
                'exception_approved_at' => now(),
                'exception_approval_comment' => $comment,
                'assessed_by' => $locked->assessed_by,
                'assessed_at' => $locked->assessed_at,
                'change_reason' => 'Authorized evidence exception approval: '.$comment,
                'lock_version' => 1,
            ]);
            $revision->load(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
            $this->support->event($request, $engagement, 'EVIDENCE_EXCEPTION_APPROVED', 'ASSESSED', 'ASSESSED', ['versionNumber' => $locked->version_number], $this->assessmentValues($revision), $comment, 'AEMS_EVIDENCE_ASSESSMENT', $revision->id, $revision->version_number, $revision->evidence?->evidence_code, $revision->assessment_family_uuid, [$revision->document_version_id]);
            $this->support->audit($request, 'aems.evidence.exception_approved', $engagement, null, $this->assessmentValues($revision), ['assessmentId' => $revision->id, 'supersedesAssessmentId' => $locked->id]);
            $this->notifications->evidenceAssessmentRecorded($request, $engagement, $revision, 'EXCEPTION_APPROVED');
            return $revision;
        }, 3);
        return $updated->fresh(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
    }

    /** @return array<string, mixed> */
    public function requestData(AemsEvidenceRequest $record): array
    {
        $record = $this->loadRequest($record);
        return [
            'id' => $record->id, 'familyUuid' => $record->request_family_uuid,
            'requestCode' => $record->request_code, 'title' => $record->title,
            'purpose' => $record->purpose, 'status' => $record->status,
            'dueDate' => $record->due_date?->toDateString(),
            'currentVersionNumber' => $record->current_version_number,
            'requestedFromOffice' => $record->requestedFromOffice?->only(['id', 'code', 'name']),
            'requestedFromUser' => $this->user($record->requestedFromUser),
            'preparedBy' => $this->user($record->preparer), 'submittedBy' => $this->user($record->submitter),
            'sentBy' => $this->user($record->sender), 'closedBy' => $this->user($record->closer),
            'submittedAt' => $record->submitted_at?->toIso8601String(), 'sentAt' => $record->sent_at?->toIso8601String(),
            'partiallyReceivedAt' => $record->partially_received_at?->toIso8601String(), 'receivedAt' => $record->received_at?->toIso8601String(),
            'assessedAt' => $record->assessed_at?->toIso8601String(), 'closedAt' => $record->closed_at?->toIso8601String(),
            'closureReason' => $record->closure_reason, 'lockVersion' => $record->lock_version,
            'latestVersion' => $record->latestVersion ? $this->versionData($record->latestVersion) : null,
            'versions' => $record->versions->map(fn (AemsEvidenceRequestVersion $version): array => $this->versionData($version))->values(),
            'evidence' => $record->evidenceLinks->map(fn (AemsEvidenceRequestEvidence $link): array => $this->linkData($link))->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function assessmentData(AemsEvidenceAssessment $assessment): array
    {
        $assessment->loadMissing(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
        $eligibility = $this->evidenceEligibility($assessment);
        return [
            'id' => $assessment->id, 'familyUuid' => $assessment->assessment_family_uuid,
            'evidenceId' => $assessment->audit_evidence_id, 'evidenceCode' => $assessment->evidence?->evidence_code,
            'evidenceRequestId' => $assessment->evidence_request_id, 'documentVersionId' => $assessment->document_version_id,
            'versionNumber' => $assessment->version_number, 'isCurrentRevision' => $assessment->is_current_revision,
            'status' => $assessment->status, 'sufficiency' => $assessment->sufficiency,
            'appropriateness' => $assessment->appropriateness, 'relevance' => $assessment->relevance,
            'reliability' => $assessment->reliability, 'competence' => $assessment->competence,
            'accuracy' => $assessment->accuracy, 'completeness' => $assessment->completeness,
            'corroboration' => $assessment->corroboration, 'contradiction' => $assessment->contradiction,
            'authenticity' => $assessment->authenticity, 'integrity' => $assessment->integrity,
            'confidentiality' => $assessment->confidentiality, 'isRestricted' => $assessment->is_restricted,
            'accessRestrictions' => $assessment->access_restrictions, 'limitations' => $assessment->limitations,
            'evidenceGaps' => $assessment->evidence_gaps, 'exceptionRequired' => $assessment->exception_required,
            'exceptionReason' => $assessment->exception_reason, 'exceptionApprovedBy' => $this->user($assessment->exceptionApprover),
            'exceptionApprovedAt' => $assessment->exception_approved_at?->toIso8601String(),
            'exceptionApprovalComment' => $assessment->exception_approval_comment,
            'assessedBy' => $this->user($assessment->assessor), 'assessedAt' => $assessment->assessed_at?->toIso8601String(),
            'changeReason' => $assessment->change_reason, 'lockVersion' => $assessment->lock_version,
            'eligibleForFinalizedFinding' => $eligibility['eligible'],
            'eligibilityReasons' => $eligibility['reasons'],
        ];
    }

    public function eligibleForFinalizedFinding(?AemsEvidenceAssessment $assessment): bool
    {
        return $this->evidenceEligibility($assessment)['eligible'];
    }

    /** @return array{eligible: bool, reasons: list<string>} */
    public function evidenceEligibility(?AemsEvidenceAssessment $assessment): array
    {
        if (! $assessment) {
            return ['eligible' => false, 'reasons' => ['No professional evidence assessment has been recorded.']];
        }
        $assessment->loadMissing(['evidence', 'documentVersion']);
        $reasons = [];
        if (! $assessment->is_current_revision || $assessment->status !== 'ASSESSED') {
            $reasons[] = 'The assessment is not the current assessed version.';
        }
        $evidence = $assessment->evidence;
        if (! $evidence || ! $evidence->is_current_revision || ! in_array($evidence->status, ['VERIFIED', 'LOCKED'], true)) {
            $reasons[] = 'The Evidence record is not a current verified or locked version.';
        }
        if (! $assessment->documentVersion || (int) $assessment->document_version_id !== (int) $evidence?->document_version_id) {
            $reasons[] = 'The assessment does not cite the exact current Core Document Version.';
        }
        foreach (self::ASSESSMENT_DIMENSIONS as $dimension) {
            $value = strtoupper(trim((string) $assessment->{$dimension}));
            $acceptable = $dimension === 'contradiction'
                ? in_array($value, ['NO', 'ADEQUATE'], true)
                : in_array($value, ['YES', 'HIGH', 'ADEQUATE'], true);
            if (! $acceptable) {
                $reasons[] = str($dimension)->headline()->toString().' is incomplete or professionally negative.';
            }
        }
        if (blank($assessment->confidentiality)) {
            $reasons[] = 'Evidence confidentiality must be classified.';
        }
        if (filled($assessment->evidence_gaps)) {
            $reasons[] = 'Unresolved evidence gaps remain.';
        }
        if (filled($assessment->limitations) && ! $assessment->exception_approved_at) {
            $reasons[] = 'Evidence limitations require an approved exception or resolution.';
        }
        if (($assessment->is_restricted || filled($assessment->access_restrictions) || $assessment->exception_required)
            && ! $assessment->exception_approved_at) {
            $reasons[] = 'Restricted or exception-controlled evidence requires separate approval.';
        }

        return ['eligible' => $reasons === [], 'reasons' => array_values(array_unique($reasons))];
    }

    /** @return list<string> */
    private function requestRelations(): array
    {
        return ['requestedFromOffice', 'requestedFromUser', 'preparer', 'submitter', 'sender', 'closer', 'latestVersion.creator', 'versions.creator', 'evidenceLinks.evidence.documentVersion', 'evidenceLinks.documentVersion', 'evidenceLinks.receiver'];
    }

    private function loadRequest(AemsEvidenceRequest $record): AemsEvidenceRequest
    {
        return $record->fresh($this->requestRelations());
    }

    /** @param array<string, mixed> $attributes */
    private function requestAttributes(array $attributes): array
    {
        return ['title' => $attributes['title'], 'purpose' => $attributes['purpose'], 'requested_from_office_id' => $attributes['requestedFromOfficeId'] ?? null, 'requested_from_user_id' => $attributes['requestedFromUserId'] ?? null, 'due_date' => $attributes['dueDate'] ?? null];
    }

    /** @param array<string, mixed> $attributes */
    private function createVersion(Request $request, AemsEvidenceRequest $record, array $attributes, int $number, ?string $reason): AemsEvidenceRequestVersion
    {
        return AemsEvidenceRequestVersion::query()->create([
            'evidence_request_id' => $record->id, 'version_number' => $number, ...$this->requestAttributes($attributes),
            'requested_items' => array_values($attributes['requestedItems'] ?? []), 'change_reason' => $reason,
            'created_by' => $request->user()->id, 'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function assessmentAttributes(array $attributes): array
    {
        return collect(['sufficiency', 'appropriateness', 'relevance', 'reliability', 'competence', 'accuracy', 'completeness', 'corroboration', 'contradiction', 'authenticity', 'integrity', 'confidentiality'])
            ->mapWithKeys(fn (string $key): array => [$key => $attributes[$key] ?? null])->all() + [
                'is_restricted' => (bool) ($attributes['isRestricted'] ?? false),
                'access_restrictions' => $attributes['accessRestrictions'] ?? null,
                'limitations' => $attributes['limitations'] ?? null,
                'evidence_gaps' => $attributes['evidenceGaps'] ?? null,
                'exception_required' => (bool) ($attributes['exceptionRequired'] ?? false),
                'exception_reason' => $attributes['exceptionReason'] ?? null,
            ];
    }

    private function assertOfficeAndUser(AuditEngagement $engagement, array $attributes): void
    {
        if (! empty($attributes['requestedFromOfficeId']) && ! $engagement->offices()->whereKey((int) $attributes['requestedFromOfficeId'])->exists()) throw ValidationException::withMessages(['requestedFromOfficeId' => ['The requested office must be covered by this engagement.']]);
        if (! empty($attributes['requestedFromUserId']) && ! $engagement->teamMembers()->where('user_id', (int) $attributes['requestedFromUserId'])->where('is_active', true)->whereNull('ended_at')->exists()) throw ValidationException::withMessages(['requestedFromUserId' => ['The requested user must be an active engagement participant.']]);
    }

    private function lockRequest(AuditEngagement $engagement, AemsEvidenceRequest $record, int $lockVersion): AemsEvidenceRequest
    {
        $locked = AemsEvidenceRequest::query()->lockForUpdate()->findOrFail($record->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id) throw ValidationException::withMessages(['request' => ['The Evidence Request does not belong to this engagement.']]);
        if ($locked->lock_version !== $lockVersion) throw ValidationException::withMessages(['lockVersion' => ['This Evidence Request changed in another session. Refresh before continuing.']]);
        $locked->load($this->requestRelations());
        return $locked;
    }

    private function ensureRequestEvidenceAssessed(AemsEvidenceRequest $record): void
    {
        foreach ($record->evidenceLinks as $link) {
            $assessment = $link->evidence?->currentAssessment;
            if ((int) $link->document_version_id !== (int) $assessment?->document_version_id || ! $this->eligibleForFinalizedFinding($assessment)) throw ValidationException::withMessages(['evidence' => ['Every received Evidence/Core Document Version must have an eligible assessment before this request can be assessed.']]);
        }
    }

    private function supersedeAssessment(AemsEvidenceAssessment $assessment): void
    {
        DB::table('aems_evidence_assessments')
            ->where('id', $assessment->id)
            ->where('is_current_revision', true)
            ->update(['is_current_revision' => false, 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function assessmentSnapshotAttributes(AemsEvidenceAssessment $assessment): array
    {
        return collect([
            ...self::ASSESSMENT_DIMENSIONS,
            'confidentiality', 'is_restricted', 'access_restrictions', 'limitations',
            'evidence_gaps', 'exception_required', 'exception_reason',
        ])->mapWithKeys(fn (string $field): array => [$field => $assessment->{$field}])->all();
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        $number = AemsEvidenceRequest::withTrashed()->where('audit_engagement_id', $engagement->id)->count() + 1;
        do { $code = sprintf('ERQ-%s-%03d', $engagement->engagement_code, $number++); } while (AemsEvidenceRequest::withTrashed()->where('audit_engagement_id', $engagement->id)->where('request_code', $code)->exists());
        return $code;
    }

    private function event(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, string $action, ?string $from, ?string $to, ?string $comment = null, ?array $documentIds = null): void
    {
        $this->support->event($request, $engagement, 'EVIDENCE_REQUEST_'.$action, $from, $to, ['requestCode' => $record->request_code, 'status' => $from], ['requestCode' => $record->request_code, 'status' => $to], $comment, 'AEMS_EVIDENCE_REQUEST', $record->id, $record->current_version_number, $record->request_code, $record->request_family_uuid, $documentIds);
    }

    private function auditValues(AemsEvidenceRequest $record): array { return ['id' => $record->id, 'requestCode' => $record->request_code, 'status' => $record->status, 'versionNumber' => $record->current_version_number, 'lockVersion' => $record->lock_version]; }
    private function assessmentValues(AemsEvidenceAssessment $assessment): array { return ['id' => $assessment->id, 'evidenceId' => $assessment->audit_evidence_id, 'status' => $assessment->status, 'versionNumber' => $assessment->version_number, 'documentVersionId' => $assessment->document_version_id, 'isRestricted' => $assessment->is_restricted, 'exceptionApprovedAt' => $assessment->exception_approved_at?->toIso8601String()]; }

    private function versionData(AemsEvidenceRequestVersion $version): array { return ['id' => $version->id, 'versionNumber' => $version->version_number, 'title' => $version->title, 'purpose' => $version->purpose, 'requestedFromOfficeId' => $version->requested_from_office_id, 'requestedFromUserId' => $version->requested_from_user_id, 'dueDate' => $version->due_date?->toDateString(), 'requestedItems' => $version->requested_items ?? [], 'changeReason' => $version->change_reason, 'createdBy' => $this->user($version->creator), 'createdAt' => $version->created_at?->toIso8601String()]; }
    private function linkData(AemsEvidenceRequestEvidence $link): array { return ['id' => $link->id, 'evidenceId' => $link->audit_evidence_id, 'evidenceCode' => $link->evidence?->evidence_code, 'documentVersionId' => $link->document_version_id, 'fileName' => $link->documentVersion?->original_file_name, 'checksumSha256' => $link->documentVersion?->checksum_sha256, 'receivedBy' => $this->user($link->receiver), 'receivedAt' => $link->received_at?->toIso8601String(), 'receiptNotes' => $link->receipt_notes, 'assessment' => $link->evidence?->currentAssessment ? $this->assessmentData($link->evidence->currentAssessment) : null]; }
    private function evidenceSummary(AuditEvidence $evidence): array { return ['id' => $evidence->id, 'evidenceCode' => $evidence->evidence_code, 'title' => $evidence->title, 'status' => $evidence->status, 'versionNumber' => $evidence->version_number, 'documentVersionId' => $evidence->document_version_id, 'assessment' => $evidence->currentAssessment ? $this->assessmentData($evidence->currentAssessment) : null]; }
    private function user(mixed $user): ?array { return $user ? ['id' => $user->id, 'employeeId' => $user->employee_id, 'name' => $user->name, 'initials' => $user->initials] : null; }
}
