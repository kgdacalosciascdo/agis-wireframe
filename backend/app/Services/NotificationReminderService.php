<?php

namespace App\Services;

use App\Models\IapPlanEngagement;
use App\Models\User;
use App\Models\WorkflowInstance;

/**
 * Generates deduplicated due-date and workflow reminder notifications.
 */
class NotificationReminderService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function dispatch(): int
    {
        $delivered = 0;

        WorkflowInstance::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addHours(48))
            ->with(['definition:id,name', 'currentStep.responsibleRole'])
            ->each(function (WorkflowInstance $instance) use (&$delivered): void {
                $overdue = $instance->due_at->isPast();
                $recipients = collect([$instance->started_by]);
                if ($instance->currentStep->responsibleRole) {
                    $code = $instance->currentStep->responsibleRole->code;
                    $recipients = $recipients->merge(
                        User::query()
                            ->where('is_active', true)
                            ->when(
                                $instance->office_id,
                                fn ($query) => $query->where('office_id', $instance->office_id),
                            )
                            ->where(function ($query) use ($code): void {
                                $query
                                    ->whereHas('roles', fn ($role) => $role->where('code', $code))
                                    ->orWhereHas('role', fn ($role) => $role->where('code', $code));
                            })
                            ->pluck('id'),
                    );
                }
                $delivered += $this->notifications->send($recipients->filter()->unique(), [
                    'type' => $overdue ? 'WORKFLOW_OVERDUE' : 'WORKFLOW_DUE',
                    'category' => $overdue ? 'OVERDUE' : 'DUE_DATE',
                    'priority' => $overdue ? 'URGENT' : 'HIGH',
                    'moduleCode' => $instance->module_code,
                    'title' => $overdue
                        ? "Overdue: {$instance->subject_code}"
                        : "Due soon: {$instance->subject_code}",
                    'message' => "{$instance->currentStep->name} is due {$instance->due_at->diffForHumans()}.",
                    'actionUrl' => "/workflow-management?instance={$instance->id}",
                    'actionLabel' => 'Review workflow',
                    'subjectType' => $instance->subject_type,
                    'subjectId' => $instance->subject_id,
                    'subjectCode' => $instance->subject_code,
                    'dedupeKey' => "workflow:{$instance->id}:due:{$instance->due_at->toDateString()}:".($overdue ? 'overdue' : 'upcoming'),
                ])->count();
            });

        IapPlanEngagement::query()
            ->where('schedule_status', 'SCHEDULED')
            ->whereDate('planned_start_date', '>=', today())
            ->whereDate('planned_start_date', '<=', today()->addDays(7))
            ->with(['teamMembers', 'plan:id,plan_code'])
            ->each(function (IapPlanEngagement $engagement) use (&$delivered): void {
                $delivered += $this->notifications->send(
                    $engagement->teamMembers->pluck('user_id'),
                    [
                        'type' => 'AUDIT_START_REMINDER',
                        'category' => 'DUE_DATE',
                        'priority' => 'HIGH',
                        'moduleCode' => 'IAP',
                        'title' => "Upcoming audit: {$engagement->engagement_code}",
                        'message' => "{$engagement->title} starts {$engagement->planned_start_date->diffForHumans()}.",
                        'actionUrl' => "/internal-audit-planning/{$engagement->plan_id}",
                        'actionLabel' => 'Open annual plan',
                        'subjectType' => 'IAP_PLAN_ENGAGEMENT',
                        'subjectId' => $engagement->id,
                        'subjectCode' => $engagement->engagement_code,
                        'dedupeKey' => "iap-engagement:{$engagement->id}:start:{$engagement->planned_start_date->toDateString()}",
                    ],
                )->count();
            });

        return $delivered;
    }
}
