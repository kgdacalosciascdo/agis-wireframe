<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditReport;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Centralizes AEMS role, assignment, office, report-recipient, and separation-of-duty access.
 */
class AemsAccessService
{
    /** @var list<string> */
    private const CIAS_ONLY_PERMISSIONS = [
        'aems.engagement.create',
        'aems.engagement.authorize',
        'aems.engagement.suspend',
        'aems.engagement.cancel',
        'aems.engagement.archive',
        'aems.engagement.restore',
        'aems.engagement.close',
        'aems.entry-conference.waive',
        'aems.team.assign',
        'aems.team.reassign',
        'aems.aeo.approve',
        'aems.aeo.issue',
        'aems.aeo.revise',
        'aems.aep.approve',
        'aems.aep.revise',
        'aems.program.approve',
        'aems.evidence.void',
        'aems.finding.communicate',
        'aems.finding.finalize',
        'aems.rejoinder.finalize',
        'aems.report.approve',
        'aems.report.issue',
        'aems.completion-assessment.approve',
        'aems.closure.approve',
        'aems.closure.close',
        'aems.document-index.finalize',
        'aems.retention.approve',
        'aems.engagement.reopen_approve',
    ];

    /** @var list<string> */
    private const INDEPENDENT_ACTIONS = [
        'aems.aeo.review',
        'aems.aeo.approve',
        'aems.aeo.issue',
        'aems.engagement.authorize',
        'aems.aep.review',
        'aems.aep.approve',
        'aems.program.review',
        'aems.program.approve',
        'aems.working-paper.review',
        'aems.working-paper.approve',
        'aems.issue.validate',
        'aems.issue.dismiss',
        'aems.issue.convert',
        'aems.finding.review',
        'aems.finding.validate',
        'aems.finding.communicate',
        'aems.finding.finalize',
        'aems.report.review',
        'aems.report.approve',
        'aems.report.issue',
        'aems.engagement.close',
        'aems.completion-assessment.review',
        'aems.completion-assessment.approve',
        'aems.closure.review',
        'aems.closure.approve',
        'aems.closure.close',
        'aems.retention.approve',
        'aems.engagement.reopen_approve',
    ];

    /** @var array<string, list<string>> */
    private const ASSIGNMENT_ROLES = [
        'aems.engagement.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.engagement.update' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.engagement.transition' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.team.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.aeo.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.aeo.prepare' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.aeo.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.aep.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.aep.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.aep.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.program.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.program.manage' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.program.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.working-paper.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.working-paper.create' => ['TEAM_LEADER', 'AUDITOR'],
        'aems.working-paper.review' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.working-paper.approve' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.evidence.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.evidence.upload' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence.verify' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.issue.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.issue.validate' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.dismiss' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.convert' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.finding.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.finding.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.finding.review' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.finding.validate' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.management-response.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.management-response.request_clarification' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.rejoinder.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.rejoinder.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.rejoinder.finalize' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.conference.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.conference.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.entry-conference.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.entry-conference.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.entry-conference.acknowledge' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.report.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.report.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.report.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.report.view_issued' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.completion-assessment.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.completion-assessment.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-assessment.update' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-assessment.submit' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-assessment.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.closure.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.closure.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.closure.update' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.closure.submit' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.closure.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.document-index.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.document-index.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.retention.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.retention.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.engagement.reopen_request' => ['SUPERVISOR', 'TEAM_LEADER'],
    ];

    public function visibleEngagements(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('aems.engagement.view')) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasGlobalEngagementAccess()) {
            return $query;
        }

        return $query->whereHas(
            'teamMembers',
            fn (Builder $team): Builder => $team
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('ended_at'),
        );
    }

    public function authorizeEngagementView(User $user, AuditEngagement $engagement): void
    {
        $allowed = $this->visibleEngagements(
            AuditEngagement::query()->withTrashed()->whereKey($engagement->id),
            $user,
        )->exists();

        throw_unless($allowed, new HttpException(403, 'This engagement is outside your AEMS access scope.'));
    }

    public function authorizeEngagementAction(
        User $user,
        AuditEngagement $engagement,
        string $permission,
        ?int $originatorId = null,
    ): void {
        throw_unless(
            $user->hasPermission($permission),
            new HttpException(403, 'You do not have the required AEMS permission.'),
        );

        if ($engagement->status === 'CLOSED'
            && ! str_ends_with($permission, '.view')
            && ! in_array($permission, [
                'aems.engagement.reopen_request',
                'aems.engagement.reopen_approve',
            ], true)) {
            throw ValidationException::withMessages([
                'engagement' => ['Closed engagement official records are immutable.'],
            ]);
        }

        if (in_array($permission, self::CIAS_ONLY_PERMISSIONS, true)) {
            throw_unless(
                $user->hasRole('cias_management'),
                new HttpException(403, 'This AEMS action requires CIAS Management authority.'),
            );
        } elseif ($user->hasGlobalEngagementAccess()
            && str_ends_with($permission, '.view')) {
            // Global administrators may monitor AEMS records but still receive
            // no operational or approval authority from this read-only branch.
        } elseif (! $user->hasRole('cias_management')) {
            $allowedRoles = self::ASSIGNMENT_ROLES[$permission] ?? [];
            throw_unless(
                $this->hasAssignmentRole($user, $engagement, $allowedRoles),
                new HttpException(403, 'Your engagement assignment does not allow this action.'),
            );
        }

        $this->enforceSeparationOfDuties($user, $permission, $originatorId);
    }

    public function visibleFindings(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('aems.finding.view')) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole('cias_management')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            if ($user->hasRole('agis_user')) {
                $visible->whereHas(
                    'engagement.teamMembers',
                    fn (Builder $team): Builder => $team
                        ->where('user_id', $user->id)
                        ->where('is_active', true)
                        ->whereNull('ended_at'),
                );
            }
            if ($user->hasRole('auditee_representative') && $user->office_id) {
                $visible->orWhere(function (Builder $auditee) use ($user): void {
                    $auditee
                        ->where('responsible_office_id', $user->office_id)
                        ->whereIn('status', [
                            'COMMUNICATED',
                            'AWAITING_MANAGEMENT_RESPONSE',
                            'UNDER_DIALOGUE',
                            'FINALIZED',
                        ]);
                });
            }
        });
    }

    public function authorizeFindingView(User $user, AuditFinding $finding): void
    {
        $allowed = $this->visibleFindings(
            AuditFinding::query()->withTrashed()->whereKey($finding->id),
            $user,
        )->exists();

        throw_unless($allowed, new HttpException(403, 'This finding is outside your AEMS access scope.'));
    }

    public function authorizeManagementResponseSubmit(User $user, AuditFinding $finding): void
    {
        throw_unless(
            $user->hasPermission('aems.management-response.submit')
                && $user->hasRole('auditee_representative')
                && (int) $user->office_id === (int) $finding->responsible_office_id
                && in_array($finding->status, [
                    'COMMUNICATED',
                    'AWAITING_MANAGEMENT_RESPONSE',
                    'UNDER_DIALOGUE',
                ], true),
            new HttpException(403, 'You cannot respond to this finding.'),
        );
    }

    public function authorizeConferenceView(User $user, ExitConference $conference): void
    {
        throw_unless(
            $user->hasPermission('aems.conference.view'),
            new HttpException(403, 'You cannot view AEMS conferences.'),
        );
        if ($user->hasRole('cias_management')
            || $this->isAssigned($user, $conference->engagement)) {
            return;
        }

        $covered = $user->hasRole('auditee_representative')
            && $user->office_id
            && $conference->engagement->offices()->whereKey($user->office_id)->exists();

        throw_unless($covered, new HttpException(403, 'This conference is outside your office scope.'));
    }

    public function authorizeEntryConferenceView(
        User $user,
        AuditEngagement|EntryConference $record,
    ): void {
        throw_unless(
            $user->hasPermission('aems.entry-conference.view'),
            new HttpException(403, 'You cannot view Entry Conferences.'),
        );
        $engagement = $record instanceof EntryConference ? $record->engagement : $record;
        if ($user->hasRole('cias_management') || $this->isAssigned($user, $engagement)) {
            return;
        }

        $covered = $user->hasRole('auditee_representative')
            && $user->office_id
            && $engagement->offices()->whereKey($user->office_id)->exists();

        throw_unless($covered, new HttpException(403, 'This Entry Conference is outside your office scope.'));
    }

    public function visibleReports(Builder $query, User $user): Builder
    {
        $canViewInternal = $user->hasPermission('aems.report.view');
        $canViewIssued = $user->hasPermission('aems.report.view_issued');
        if (! $canViewInternal && ! $canViewIssued) {
            return $query->whereRaw('1 = 0');
        }
        if ($canViewInternal && $user->hasRole('cias_management')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user, $canViewInternal, $canViewIssued): void {
            if ($canViewInternal && $user->hasRole('agis_user')) {
                $visible->whereHas(
                    'engagement.teamMembers',
                    fn (Builder $team): Builder => $team
                        ->where('user_id', $user->id)
                        ->where('is_active', true)
                        ->whereNull('ended_at'),
                );
            }

            if ($canViewIssued) {
                if ($user->hasGlobalEngagementAccess() && ! $user->isReadOnlyOnly()) {
                    // Platform administrators may monitor issued output, not working drafts.
                    $visible->orWhere('status', 'ISSUED');
                } else {
                    $visible->orWhere(function (Builder $issued) use ($user): void {
                        $issued->where('status', 'ISSUED')
                            ->whereHas('versions', function (Builder $version) use ($user): void {
                                $version
                                    ->whereColumn(
                                        'audit_report_versions.version_number',
                                        'audit_reports.current_version_number',
                                    )
                                    ->whereHas('recipients', function (Builder $recipient) use ($user): void {
                                        $recipient->where('user_id', $user->id);
                                        if ($user->hasRole('auditee_representative') && $user->office_id) {
                                            $recipient->orWhere('office_id', $user->office_id);
                                        }
                                    });
                            });
                    });
                }
            }
        });
    }

    public function authorizeReportView(User $user, AuditReport $report): void
    {
        $allowed = $this->visibleReports(
            AuditReport::query()->withTrashed()->whereKey($report->id),
            $user,
        )->exists();

        throw_unless($allowed, new HttpException(403, 'This report is outside your AEMS access scope.'));
    }

    /** @param list<string> $allowedRoles */
    private function hasAssignmentRole(
        User $user,
        AuditEngagement $engagement,
        array $allowedRoles,
    ): bool {
        if ($allowedRoles === []) {
            return false;
        }

        return $engagement->teamMembers()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->whereIn('assignment_role_code', $allowedRoles)
            ->exists();
    }

    private function isAssigned(User $user, AuditEngagement $engagement): bool
    {
        return $engagement->teamMembers()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->exists();
    }

    private function enforceSeparationOfDuties(
        User $user,
        string $permission,
        ?int $originatorId,
    ): void {
        if ($originatorId !== null
            && $originatorId === $user->id
            && in_array($permission, self::INDEPENDENT_ACTIONS, true)) {
            throw ValidationException::withMessages([
                'action' => ['The preparer or originator cannot perform this independent AEMS action.'],
            ]);
        }
    }
}
