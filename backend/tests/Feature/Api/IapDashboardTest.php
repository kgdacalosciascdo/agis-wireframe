<?php

namespace Tests\Feature\Api;

use App\Models\IapAuditorUnavailability;
use App\Models\IapAuditUniverseItem;
use App\Models\IapPrioritizationRun;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IapSchedulingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_aggregates_live_planning_risk_capacity_and_schedule_data(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $plan = $this->plan();
        $run = IapPrioritizationRun::query()
            ->whereKey($plan->prioritization_run_id)
            ->with('items')
            ->firstOrFail();
        $selected = $run->items->where('decision', 'SELECTED');
        $plannedSourceIds = $plan->engagements()
            ->where('is_active', true)
            ->where('schedule_status', '!=', 'CANCELLED')
            ->pluck('prioritization_item_id')
            ->filter();

        $response = $this->getJson('/api/iap/dashboard')
            ->assertOk()
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.plan.status', 'DRAFT')
            ->assertJsonPath(
                'data.metrics.totalAuditUniverse',
                IapAuditUniverseItem::query()->where('is_active', true)->count(),
            )
            ->assertJsonPath('data.metrics.selectedSubjects', $selected->count())
            ->assertJsonPath(
                'data.metrics.deferredSubjects',
                $run->items->where('decision', 'DEFERRED')->count(),
            )
            ->assertJsonPath('data.metrics.plannedAudits', $plannedSourceIds->count())
            ->assertJsonPath(
                'data.metrics.unplannedAudits',
                $selected->whereNotIn('id', $plannedSourceIds)->count(),
            )
            ->assertJsonPath('data.context.prioritization.id', $run->id)
            ->assertJsonPath('data.context.riskPeriod.id', $run->risk_period_id)
            ->assertJsonCount(4, 'data.riskDistribution')
            ->assertJsonCount(4, 'data.decisionDistribution');

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['metrics']['availablePersonDays']);
        $this->assertGreaterThan(0, $data['metrics']['allocatedPersonDays']);
        $this->assertGreaterThanOrEqual(0, $data['metrics']['capacityUtilization']);
        $this->assertSame(
            collect($data['riskDistribution'])->sum('value'),
            $data['metrics']['criticalRiskSubjects'] + $data['metrics']['highRiskSubjects']
                + collect($data['riskDistribution'])
                    ->whereIn('code', ['MEDIUM', 'LOW'])
                    ->sum('value'),
        );
    }

    public function test_dashboard_uses_the_schedule_conflict_engine_for_live_warnings(): void
    {
        $plan = $this->plan();
        $engagement = $plan->engagements()
            ->with('teamMembers')
            ->where('schedule_status', 'SCHEDULED')
            ->firstOrFail();
        $member = $engagement->teamMembers->firstOrFail();
        IapAuditorUnavailability::query()->create([
            'user_id' => $member->user_id,
            'unavailability_type_id' => $this->item(
                'IAP_UNAVAILABILITY_TYPE',
                'LEAVE',
            )->id,
            'title' => 'Approved leave during planned audit',
            'start_date' => $engagement->planned_start_date,
            'end_date' => $engagement->planned_end_date,
            'created_by' => $this->user('departmenthead')->id,
        ]);

        Sanctum::actingAs($this->user('departmenthead'));
        $this->getJson('/api/iap/dashboard')
            ->assertOk()
            ->assertJsonPath('data.conflictWarnings.0.type', 'AUDITOR_UNAVAILABLE')
            ->assertJsonPath(
                'data.conflictWarnings.0.sourceEngagementId',
                $engagement->id,
            );
    }

    public function test_dashboard_plan_visibility_is_scoped_by_role(): void
    {
        Sanctum::actingAs($this->user('mayor'));
        $this->getJson('/api/iap/dashboard')
            ->assertOk()
            ->assertJsonPath('data.plan', null)
            ->assertJsonPath('data.metrics.plannedAudits', 0);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/iap/dashboard')->assertForbidden();
    }

    private function plan(): InternalAuditPlan
    {
        return InternalAuditPlan::query()
            ->where('plan_code', IapSchedulingSeeder::DEMO_PLAN_CODE)
            ->firstOrFail();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->firstOrFail();
    }
}
