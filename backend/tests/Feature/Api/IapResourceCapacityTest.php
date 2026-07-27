<?php

namespace Tests\Feature\Api;

use App\Models\IapPlanEngagement;
use App\Models\MasterListItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapResourceCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_capacity_availability_skills_and_requirements_feed_schedule_warnings(): void
    {
        $manager = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        Sanctum::actingAs($manager);

        $resource = $this->getJson('/api/iap/resources?fiscalYear=2026')
            ->assertOk()
            ->assertJsonPath('data.fiscalYear', 2026)
            ->json('data');
        $this->assertGreaterThanOrEqual(2, $resource['summary']['totalAuditors']);
        $this->assertGreaterThan(0, $resource['summary']['availablePersonDays']);

        $this->putJson("/api/iap/resources/auditors/{$auditor->id}/capacity", [
            'fiscalYear' => 2026,
            'availablePersonDays' => 12,
            'notes' => 'Reduced for leave and mandatory training.',
        ])->assertOk();

        $leave = $this->postJson(
            "/api/iap/resources/auditors/{$auditor->id}/unavailability",
            [
                'typeId' => $this->item('IAP_UNAVAILABILITY_TYPE', 'LEAVE')->id,
                'title' => 'Approved annual leave',
                'startDate' => '2026-08-10',
                'endDate' => '2026-08-14',
                'notes' => 'Recorded for capacity and schedule conflict checking.',
            ],
        )->assertCreated();
        $leaveId = $this->getConnection()
            ->table('iap_auditor_unavailability')
            ->where('title', 'Approved annual leave')
            ->value('id');

        $this->putJson("/api/iap/resources/auditors/{$auditor->id}/skills", [
            'skills' => [[
                'specializationId' => $this->item(
                    'IAP_AUDITOR_SPECIALIZATION',
                    'COMPLIANCE',
                )->id,
                'proficiencyLevel' => 'ADVANCED',
            ]],
        ])->assertOk();

        $engagement = IapPlanEngagement::query()
            ->where('engagement_code', 'IAP-2026-001')
            ->with(['plan', 'teamMembers'])
            ->firstOrFail();
        $this->putJson("/api/iap/resources/engagements/{$engagement->id}/requirements", [
            'requirements' => [[
                'specializationId' => $this->item(
                    'IAP_AUDITOR_SPECIALIZATION',
                    'CYBERSECURITY',
                )->id,
                'minimumAuditors' => 1,
                'minimumProficiency' => 'ADVANCED',
                'notes' => 'Required for access-control and resilience testing.',
            ]],
        ])->assertOk();

        $members = $engagement->teamMembers->map(fn ($member) => [
            'userId' => $member->user_id,
            'teamRoleId' => $member->team_role_id,
            'plannedPersonDays' => (float) $member->planned_person_days,
        ])->values()->all();
        $warnings = $this->postJson("/api/iap/schedules/{$engagement->id}/conflicts", [
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-28',
            'expectedReportDate' => '2026-09-15',
            'members' => $members,
            'lockVersion' => $engagement->plan->lock_version,
        ])->assertOk()->json('data.conflicts');

        $this->assertContains('AUDITOR_UNAVAILABLE', array_column($warnings, 'type'));
        $this->assertContains('SKILL_GAP', array_column($warnings, 'type'));
        $this->assertContains('CAPACITY_EXCEEDED', array_column($warnings, 'type'));

        $this->deleteJson("/api/iap/resources/unavailability/{$leaveId}")
            ->assertOk();
        $this->assertSoftDeleted('iap_auditor_unavailability', ['id' => $leaveId]);
        $this->postJson("/api/iap/resources/unavailability/{$leaveId}/restore")
            ->assertOk();
        $this->assertDatabaseHas('iap_auditor_unavailability', [
            'id' => $leaveId,
            'deleted_at' => null,
        ]);
    }

    public function test_resource_mutations_require_management_access(): void
    {
        $auditor = $this->user('auditor');
        Sanctum::actingAs($auditor);

        $this->getJson('/api/iap/resources?fiscalYear=2026')->assertOk();
        $this->putJson("/api/iap/resources/auditors/{$auditor->id}/capacity", [
            'fiscalYear' => 2026,
            'availablePersonDays' => 150,
        ])->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()
            ->where('username', $username)
            ->with('role.permissions')
            ->firstOrFail();
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->firstOrFail();
    }
}
