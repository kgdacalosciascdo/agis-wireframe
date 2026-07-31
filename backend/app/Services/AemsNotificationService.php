<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CompletionAssessment;
use App\Models\EngagementClosure;
use App\Models\EngagementReopenRequest;
use App\Models\EngagementTeam;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\User;
use App\Models\WorkingPaper;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Routes AEMS workflow events through Core notifications. */
class AemsNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function teamAssignment(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $action,
    ): void {
        $actorId = $request->user()->id;
        $recipientId = $member->user_id;
        $assignmentRole = str($member->assignment_role_code)->replace('_', ' ')->title();
        $lockVersion = $member->updated_at?->timestamp ?? $member->id;

        DB::afterCommit(fn () => $this->notifications->send([$recipientId], [
            'actorId' => $actorId,
            'type' => 'AEMS_TEAM_'.strtoupper($action),
            'category' => 'ASSIGNMENT',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: {$assignmentRole} assignment",
            'message' => "You were {$action} as {$assignmentRole} for {$engagement->title}.",
            'actionUrl' => "/audit-engagement-management/team?engagementId={$engagement->id}",
            'actionLabel' => 'Open Audit Team',
            'subjectType' => AuditEngagement::class,
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "aems:team:{$member->id}:{$action}:{$lockVersion}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'teamMemberId' => $member->id,
                'assignmentRoleCode' => $member->assignment_role_code,
            ],
        ]));
    }

    public function controlledDocumentTransition(
        Request $request,
        AuditEngagement $engagement,
        string $documentType,
        int $documentId,
        string $documentCode,
        string $documentLabel,
        string $action,
        int $versionNumber,
        int $preparedBy,
        ?int $submittedBy,
        string $reviewPermission,
        string $actionUrl,
    ): void {
        if (! in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN', 'APPROVE'], true)) {
            return;
        }

        $recipientIds = in_array($action, ['SUBMIT', 'RESUBMIT'], true)
            ? $this->reviewers($engagement, $reviewPermission)
            : collect([$preparedBy, $submittedBy])->filter();
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $verb = match ($action) {
            'SUBMIT' => 'submitted for review',
            'RESUBMIT' => 'resubmitted for review',
            'RETURN' => 'returned for revision',
            'APPROVE' => 'approved',
        };
        $priority = in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN'], true)
            ? 'HIGH'
            : 'NORMAL';
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => "AEMS_{$documentType}_{$action}",
            'category' => 'WORKFLOW',
            'priority' => $priority,
            'moduleCode' => 'AEMS',
            'title' => "{$documentCode}: {$documentLabel} {$verb}",
            'message' => "{$documentLabel} version {$versionNumber} for {$engagement->title} was {$verb}.",
            'actionUrl' => $actionUrl,
            'actionLabel' => "Open {$documentLabel}",
            'subjectType' => $documentType,
            'subjectId' => $documentId,
            'subjectCode' => $documentCode,
            'dedupeKey' => "aems:{$documentType}:{$documentId}:v{$versionNumber}:".strtolower($action),
            'metadata' => [
                'engagementId' => $engagement->id,
                'versionNumber' => $versionNumber,
                'workflowAction' => $action,
            ],
        ]));
    }

    public function workingPaperReturned(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
        int $versionNumber,
        ?string $comment,
    ): void {
        $actorId = $request->user()->id;
        $recipientIds = collect([$paper->prepared_by, $paper->submitted_by])
            ->filter()
            ->reject(fn ($id): bool => (int) $id === (int) $actorId)
            ->unique()
            ->values();

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_WORKING_PAPER_RETURNED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$paper->working_paper_code}: Working Paper returned",
            'message' => trim("Working Paper version {$versionNumber} was returned for revision. {$comment}"),
            'actionUrl' => "/audit-engagement-management/working-papers?engagementId={$engagement->id}",
            'actionLabel' => 'Open Working Paper',
            'subjectType' => WorkingPaper::class,
            'subjectId' => $paper->id,
            'subjectCode' => $paper->working_paper_code,
            'dedupeKey' => "aems:working-paper:{$paper->id}:v{$versionNumber}:returned",
            'metadata' => [
                'engagementId' => $engagement->id,
                'versionNumber' => $versionNumber,
            ],
        ]));
    }

    public function findingCommunicated(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): void {
        $recipientIds = $this->auditeeRepresentatives(
            collect([$finding->responsible_office_id])->filter(),
        );
        $actorId = $request->user()->id;
        $dueDate = $finding->management_response_due_date?->format('M j, Y');

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_FINDING_COMMUNICATED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$finding->finding_code}: Finding communicated",
            'message' => "{$finding->title} was formally communicated"
                .($dueDate ? "; management's response is due {$dueDate}." : '.'),
            'actionUrl' => "/audit-engagement-management/auditee-responses?engagementId={$engagement->id}&findingId={$finding->id}",
            'actionLabel' => 'Open Auditee Response',
            'subjectType' => AuditFinding::class,
            'subjectId' => $finding->id,
            'subjectCode' => $finding->finding_code,
            'dedupeKey' => "aems:finding:{$finding->id}:communicated:{$finding->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'responsibleOfficeId' => $finding->responsible_office_id,
                'responseDueDate' => $finding->management_response_due_date?->toDateString(),
            ],
        ]));
    }

    public function exitConferenceScheduled(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        string $action,
    ): void {
        $conference->loadMissing('participants');
        $recipientIds = $conference->participants->pluck('user_id')->filter()
            ->merge($engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id'))
            ->merge($this->auditeeRepresentatives(
                $conference->participants->pluck('office_id')->filter()
                    ->merge($engagement->offices()->pluck('offices.id')),
            ))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;
        $rescheduled = $action === 'RESCHEDULED';

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => $rescheduled
                ? 'AEMS_EXIT_CONFERENCE_RESCHEDULED'
                : 'AEMS_EXIT_CONFERENCE_SCHEDULED',
            'category' => 'DUE_DATE',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$conference->conference_code}: Exit Conference "
                .($rescheduled ? 'rescheduled' : 'scheduled'),
            'message' => "The Exit Conference for {$engagement->title} is scheduled for "
                .$conference->scheduled_start_at?->format('M j, Y g:i A').'.',
            'actionUrl' => "/audit-engagement-management/exit-conferences?engagementId={$engagement->id}",
            'actionLabel' => 'Open Exit Conference',
            'subjectType' => ExitConference::class,
            'subjectId' => $conference->id,
            'subjectCode' => $conference->conference_code,
            'dedupeKey' => "aems:conference:{$conference->id}:"
                .$conference->scheduled_start_at?->format('YmdHi'),
            'metadata' => [
                'engagementId' => $engagement->id,
                'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
            ],
        ]));
    }

    public function reportIssued(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): void {
        $version->loadMissing('recipients');
        $userIds = $version->recipients->pluck('user_id')->filter();
        $officeIds = $version->recipients->pluck('office_id')->filter();
        if ($officeIds->isNotEmpty()) {
            $officeRecipients = User::query()
                ->whereIn('office_id', $officeIds)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereHas('roles', fn ($role) => $role->where('code', 'auditee_representative'))
                        ->orWhereHas('role', fn ($role) => $role->where('code', 'auditee_representative'));
                })
                ->pluck('id');
            $userIds = $userIds->merge($officeRecipients);
        }
        $recipientIds = $userIds->map(fn ($id): int => (int) $id)->unique()->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_REPORT_ISSUED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$report->report_code}: Final Report issued",
            'message' => "The Final Audit Report for {$engagement->title} has been issued.",
            'actionUrl' => "/audit-engagement-management/reports?engagementId={$engagement->id}",
            'actionLabel' => 'Open Audit Report',
            'subjectType' => AuditReport::class,
            'subjectId' => $report->id,
            'subjectCode' => $report->report_code,
            'dedupeKey' => "aems:report:{$report->id}:issued:v{$version->version_number}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'reportVersionId' => $version->id,
                'reportVersionNumber' => $version->version_number,
            ],
        ]));
    }

    public function engagementTransition(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        string $fromStatus,
        string $toStatus,
    ): void {
        $recipientIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id')
            ->merge($this->auditeeRepresentatives($engagement->offices()->pluck('offices.id')))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_ENGAGEMENT_'.strtoupper($action),
            'category' => 'WORKFLOW',
            'priority' => in_array($action, ['SUSPEND', 'CANCEL'], true) ? 'URGENT' : 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: "
                .str($toStatus)->replace('_', ' ')->title(),
            'message' => "{$engagement->title} moved from "
                .str($fromStatus)->replace('_', ' ')->title().' to '
                .str($toStatus)->replace('_', ' ')->title().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=lifecycle",
            'actionLabel' => 'Open Engagement Lifecycle',
            'subjectType' => AuditEngagement::class,
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "aems:engagement:{$engagement->id}:{$action}:{$engagement->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'fromStatus' => $fromStatus,
                'toStatus' => $toStatus,
            ],
        ]));
    }

    public function entryConference(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        string $action,
    ): void {
        $conference->loadMissing('participants');
        $recipientIds = $conference->participants->pluck('user_id')->filter()
            ->merge($this->auditeeRepresentatives(
                $conference->participants->pluck('office_id')->filter(),
            ))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_ENTRY_CONFERENCE_'.strtoupper($action),
            'category' => $action === 'SCHEDULE' || $action === 'RESCHEDULE'
                ? 'DUE_DATE'
                : 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$conference->conference_code}: Entry Conference "
                .str($action)->replace('_', ' ')->lower(),
            'message' => "The Entry Conference for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/entry-conference/{$engagement->id}",
            'actionLabel' => 'Open Entry Conference',
            'subjectType' => EntryConference::class,
            'subjectId' => $conference->id,
            'subjectCode' => $conference->conference_code,
            'dedupeKey' => "aems:entry-conference:{$conference->id}:{$action}:{$conference->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'conferenceStatus' => $conference->status,
            ],
        ]));
    }

    public function completionAssessment(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        string $action,
    ): void {
        if (! in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN', 'APPROVE'], true)) {
            return;
        }
        $recipientIds = in_array($action, ['SUBMIT', 'RESUBMIT'], true)
            ? $this->reviewers($engagement, 'aems.completion-assessment.review')
            : collect([$assessment->prepared_by, $assessment->submitted_by])->filter();
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_COMPLETION_ASSESSMENT_'.$action,
            'category' => 'WORKFLOW',
            'priority' => in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$assessment->assessment_code}: Completion Assessment "
                .str($action)->replace('_', ' ')->lower(),
            'message' => "The Completion Assessment for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=completion-assessment",
            'actionLabel' => 'Open Completion Assessment',
            'subjectType' => CompletionAssessment::class,
            'subjectId' => $assessment->id,
            'subjectCode' => $assessment->assessment_code,
            'dedupeKey' => "aems:completion-assessment:{$assessment->id}:{$action}:{$assessment->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    public function closure(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        string $action,
    ): void {
        if (! in_array($action, [
            'SUBMIT_CLOSURE',
            'RESUBMIT_CLOSURE',
            'RETURN_CLOSURE',
            'APPROVE_CLOSURE',
            'CLOSE_ENGAGEMENT',
        ], true)) {
            return;
        }
        $recipientIds = in_array($action, ['SUBMIT_CLOSURE', 'RESUBMIT_CLOSURE'], true)
            ? $this->reviewers($engagement, 'aems.closure.review')
            : $engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id');
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_'.$action,
            'category' => 'WORKFLOW',
            'priority' => $action === 'CLOSE_ENGAGEMENT' ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$closure->closure_code}: "
                .str($action)->replace('_', ' ')->title(),
            'message' => "The formal Closure for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=closure",
            'actionLabel' => 'Open Engagement Closure',
            'subjectType' => EngagementClosure::class,
            'subjectId' => $closure->id,
            'subjectCode' => $closure->closure_code,
            'dedupeKey' => "aems:closure:{$closure->id}:{$action}:{$closure->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    /** @param  array<int, array<string, mixed>>  $checklist */
    public function closureRecordsBlockers(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        array $checklist,
    ): void {
        $blockers = collect($checklist)
            ->filter(fn (array $item): bool => in_array(
                $item['checklistCode'] ?? null,
                ['DOCUMENT_INDEX', 'RETENTION_METADATA'],
                true,
            ) && ($item['resultCode'] ?? null) !== 'PASS');
        if ($blockers->isEmpty()) {
            return;
        }
        $recipientIds = $this->reviewers($engagement, 'aems.closure.update')
            ->merge($engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id'))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $codes = $blockers->pluck('checklistCode')->sort()->implode(':');
        $labels = $blockers->pluck('description')->implode('; ');
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_CLOSURE_RECORDS_BLOCKED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$closure->closure_code}: records action required",
            'message' => "Closure records require action: {$labels}",
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=closure",
            'actionLabel' => 'Resolve Closure Blockers',
            'subjectType' => EngagementClosure::class,
            'subjectId' => $closure->id,
            'subjectCode' => $closure->closure_code,
            'dedupeKey' => "aems:closure:{$closure->id}:records-blocked:{$codes}:{$closure->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'blockerCodes' => $blockers->pluck('checklistCode')->values()->all(),
            ],
        ]));
    }

    public function reopen(
        Request $request,
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
        string $action,
    ): void {
        $recipientIds = $action === 'SUBMIT_REOPEN_REQUEST'
            ? $this->reviewers($engagement, 'aems.engagement.reopen_approve')
            : $engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id')
                ->push($reopen->requested_by);
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_'.$action,
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$reopen->request_code}: "
                .str($action)->replace('_', ' ')->title(),
            'message' => "The exceptional reopening request for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=closure",
            'actionLabel' => 'Open Reopening Request',
            'subjectType' => EngagementReopenRequest::class,
            'subjectId' => $reopen->id,
            'subjectCode' => $reopen->request_code,
            'dedupeKey' => "aems:reopen:{$reopen->id}:{$action}:{$reopen->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    /** @return Collection<int, int> */
    private function reviewers(
        AuditEngagement $engagement,
        string $permission,
    ): Collection {
        $assignedIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id');

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($assignedIds): void {
                $query
                    ->whereIn('id', $assignedIds)
                    ->orWhereHas('roles', fn ($role) => $role->where('code', 'cias_management'))
                    ->orWhereHas('role', fn ($role) => $role->where('code', 'cias_management'));
            })
            ->with(['role.permissions', 'roles.permissions'])
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission($permission))
            ->pluck('id');
    }

    /** @return Collection<int, int> */
    private function auditeeRepresentatives(Collection $officeIds): Collection
    {
        if ($officeIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('office_id', $officeIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('roles', fn ($role) => $role->where('code', 'auditee_representative'))
                    ->orWhereHas('role', fn ($role) => $role->where('code', 'auditee_representative'));
            })
            ->pluck('id');
    }
}
