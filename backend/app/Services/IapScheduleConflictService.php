<?php

namespace App\Services;

use App\Models\IapAuditorCapacity;
use App\Models\IapAuditorSkill;
use App\Models\IapAuditorUnavailability;
use App\Models\IapPlanEngagement;
use App\Models\SystemConfiguration;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IapScheduleConflictService
{
    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @return list<array<string, mixed>>
     */
    public function detect(
        IapPlanEngagement $engagement,
        CarbonInterface $start,
        CarbonInterface $end,
        Collection $members,
    ): array {
        $engagement->loadMissing([
            'plan',
            'offices:id,code,name',
            'skillRequirements.specialization',
        ]);
        $memberIds = $members->pluck('userId')->map(fn ($id) => (int) $id);
        $officeIds = $engagement->offices->pluck('id');
        $conflicts = [];

        $overlapping = IapPlanEngagement::query()
            ->where('id', '<>', $engagement->id)
            ->where('schedule_status', 'SCHEDULED')
            ->whereDate('planned_start_date', '<=', $end->toDateString())
            ->whereDate('planned_end_date', '>=', $start->toDateString())
            ->whereHas('plan', fn ($plan) => $plan
                ->whereNull('deleted_at')
                ->where('is_current_revision', true))
            ->with([
                'plan:id,plan_code,fiscal_year',
                'offices:id,code,name',
                'teamMembers.user:id,employee_id,name,initials',
            ])
            ->get();

        foreach ($overlapping as $other) {
            $sharedAuditors = $other->teamMembers
                ->whereIn('user_id', $memberIds)
                ->pluck('user.name')
                ->filter()
                ->values();
            if ($sharedAuditors->isNotEmpty()) {
                $conflicts[] = [
                    'type' => 'AUDITOR_OVERLAP',
                    'severity' => 'danger',
                    'engagementId' => $other->id,
                    'engagementCode' => $other->engagement_code,
                    'message' => sprintf(
                        '%s already has %s assigned during the proposed dates.',
                        $other->engagement_code,
                        $sharedAuditors->join(', '),
                    ),
                ];
            }

            $sharedOffices = $other->offices
                ->whereIn('id', $officeIds)
                ->pluck('code')
                ->filter()
                ->values();
            if ($sharedOffices->isNotEmpty()) {
                $conflicts[] = [
                    'type' => 'OFFICE_OVERLAP',
                    'severity' => 'danger',
                    'engagementId' => $other->id,
                    'engagementCode' => $other->engagement_code,
                    'message' => sprintf(
                        '%s overlaps an audit of office %s.',
                        $other->engagement_code,
                        $sharedOffices->join(', '),
                    ),
                ];
            }
        }

        $unavailable = IapAuditorUnavailability::query()
            ->whereIn('user_id', $memberIds)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with(['user:id,name', 'type:id,code,label'])
            ->get();
        foreach ($unavailable as $period) {
            $conflicts[] = [
                'type' => 'AUDITOR_UNAVAILABLE',
                'severity' => 'danger',
                'userId' => $period->user_id,
                'unavailabilityId' => $period->id,
                'message' => sprintf(
                    '%s is unavailable for %s from %s to %s.',
                    $period->user?->name ?? "User #{$period->user_id}",
                    $period->type?->label ?? $period->title,
                    $period->start_date->format('M j, Y'),
                    $period->end_date->format('M j, Y'),
                ),
            ];
        }

        $proficiencyRank = [
            'BASIC' => 1,
            'INTERMEDIATE' => 2,
            'ADVANCED' => 3,
            'EXPERT' => 4,
        ];
        $skills = IapAuditorSkill::query()
            ->whereIn('user_id', $memberIds)
            ->whereIn(
                'specialization_id',
                $engagement->skillRequirements->pluck('specialization_id'),
            )
            ->get()
            ->groupBy('specialization_id');
        foreach ($engagement->skillRequirements as $requirement) {
            $qualified = $skills
                ->get($requirement->specialization_id, collect())
                ->filter(fn ($skill) => $memberIds->contains($skill->user_id)
                    && ($proficiencyRank[$skill->proficiency_level] ?? 0)
                    >= ($proficiencyRank[$requirement->minimum_proficiency] ?? 0))
                ->pluck('user_id')
                ->unique()
                ->count();
            if ($qualified < $requirement->minimum_auditors) {
                $conflicts[] = [
                    'type' => 'SKILL_GAP',
                    'severity' => 'warning',
                    'specializationId' => $requirement->specialization_id,
                    'message' => sprintf(
                        '%s requires %d auditor(s) at %s proficiency; the proposed team has %d.',
                        $requirement->specialization?->label ?? 'This specialization',
                        $requirement->minimum_auditors,
                        ucfirst(strtolower($requirement->minimum_proficiency)),
                        $qualified,
                    ),
                    'requiredAuditors' => $requirement->minimum_auditors,
                    'qualifiedAuditors' => $qualified,
                ];
            }
        }

        foreach ($members as $member) {
            $userId = (int) $member['userId'];
            $capacity = $this->capacityFor($engagement->plan->fiscal_year, $userId);
            $allocatedElsewhere = (float) DB::table('iap_engagement_team_members as team')
                ->join('iap_plan_engagements as engagement', 'engagement.id', '=', 'team.plan_engagement_id')
                ->join('internal_audit_plans as plan', 'plan.id', '=', 'engagement.plan_id')
                ->where('team.user_id', $userId)
                ->where('plan.fiscal_year', $engagement->plan->fiscal_year)
                ->where('plan.is_current_revision', true)
                ->where('engagement.schedule_status', 'SCHEDULED')
                ->whereNull('engagement.deleted_at')
                ->whereNull('plan.deleted_at')
                ->where('engagement.id', '<>', $engagement->id)
                ->sum('team.planned_person_days');
            $proposedTotal = round(
                $allocatedElsewhere + (float) $member['plannedPersonDays'],
                2,
            );
            if ($proposedTotal > $capacity) {
                $user = User::withTrashed()->find($userId);
                $conflicts[] = [
                    'type' => 'CAPACITY_EXCEEDED',
                    'severity' => 'warning',
                    'userId' => $userId,
                    'message' => sprintf(
                        '%s would have %.2f assigned person-days against %.2f available.',
                        $user?->name ?? "User #{$userId}",
                        $proposedTotal,
                        $capacity,
                    ),
                    'allocatedPersonDays' => $proposedTotal,
                    'availablePersonDays' => $capacity,
                ];
            }
        }

        return $conflicts;
    }

    /** @return array<string, float> */
    public function capacitySnapshot(int $fiscalYear, Collection $users): array
    {
        return $users->mapWithKeys(fn ($user) => [
            (string) $user->id => $this->capacityFor($fiscalYear, $user->id),
        ])->all();
    }

    public function capacityFor(int $fiscalYear, int $userId): float
    {
        $configured = IapAuditorCapacity::query()
            ->where('fiscal_year', $fiscalYear)
            ->where('user_id', $userId)
            ->value('available_person_days');
        if ($configured !== null) {
            return (float) $configured;
        }

        return (float) (SystemConfiguration::query()
            ->where('key', 'iap_default_annual_person_days')
            ->value('value') ?? 180);
    }
}
