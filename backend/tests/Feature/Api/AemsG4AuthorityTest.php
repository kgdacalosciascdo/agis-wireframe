<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\IapPlanEngagement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsG4AuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_aeo_signatories_distribution_and_team_controls_are_auditable(): void
    {
        [$management, $engagement, $team] = $this->bootstrap();
        $preparer = $team[1];
        $reviewer = $team[3];
        $issuer = $this->newManagement('g4-issuer');

        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", [
            'authority' => 'Authority under the approved annual plan and CIAS mandate.',
            'objectives' => 'Assess the design and operation of the approved controls.',
            'scope' => 'Approved transactions, records, systems, and responsible offices.',
        ])->assertCreated();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertOk()->json('data.order');
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition", [
            'action' => 'SUBMIT', 'lockVersion' => $order['lockVersion'],
        ])->assertOk();

        Sanctum::actingAs($reviewer);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition", [
            'action' => 'REVIEW', 'lockVersion' => $order['lockVersion'], 'comment' => 'Independent authority review completed.',
        ])->assertOk();

        Sanctum::actingAs($management);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition", [
            'action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Approved by the independent approving authority.',
        ])->assertOk();

        Sanctum::actingAs($issuer);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition", [
            'action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued under the approved signatory matrix.',
        ])->assertOk();

        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');

        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertJsonCount(3, 'data.order.signatories')
            ->json('data.order');
        $this->assertSame(['SIGNED', 'SIGNED', 'SIGNED'], collect($workspace['signatories'])->pluck('status')->all());

        Sanctum::actingAs($management);
        $distribution = $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/distribution", [
            'lockVersion' => $order['lockVersion'],
            'recipientType' => 'OFFICE',
            'recipientOfficeId' => $engagement->offices()->value('offices.id'),
            'transmittalMethod' => 'SECURE_PORTAL',
            'transmittalReference' => 'G4-TRANSMITTAL-001',
        ])->assertCreated()->json('data.distribution');
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/distribution/{$distribution['id']}/acknowledge", [
            'note' => 'Received by the authorized office representative.',
        ])->assertOk()->assertJsonPath('data.distribution.status', 'ACKNOWLEDGED');

        $this->assertDatabaseHas('aems_team_amendments', [
            'audit_engagement_id' => $engagement->id,
            'action' => 'ASSIGNED',
            'authority_code' => 'AEMS_TEAM_ASSIGNMENT_AUTHORITY',
        ]);
        $this->assertDatabaseHas('aems_team_access_history', [
            'audit_engagement_id' => $engagement->id,
            'action' => 'GRANTED',
        ]);
        $this->assertDatabaseHas('aems_aeo_distributions', [
            'audit_engagement_order_id' => $order['id'],
            'status' => 'ACKNOWLEDGED',
        ]);

        Sanctum::actingAs($management);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition", [
            'action' => 'SUPERSEDE',
            'lockVersion' => $order['lockVersion'],
            'comment' => 'Superseded by the controlled replacement AEO.',
        ])->assertOk()->assertJsonPath('data.order.status', 'SUPERSEDED');
        $this->assertDatabaseHas('audit_engagement_orders', ['id' => $order['id'], 'is_active' => false, 'status' => 'SUPERSEDED']);
    }

    /** @return array{User, AuditEngagement, list<User>} */
    private function bootstrap(): array
    {
        $management = $this->user('departmenthead');
        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE', 'approved_at' => now()->subDay(),
            'approved_by' => $management->id, 'activated_at' => now(), 'activated_by' => $management->id,
        ]);
        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', ['iapPlanEngagementId' => $source->id])
            ->assertCreated()->json('data.engagement.id');
        $engagement = AuditEngagement::query()->findOrFail($engagementId);
        $users = User::query()->whereHas('role', fn ($query) => $query->where('code', 'agis_user'))->take(4)->get()->values();
        $auditorRole = Role::query()->where('code', 'agis_user')->firstOrFail();
        while ($users->count() < 4) {
            $user = User::factory()->create([
                'role_id' => $auditorRole->id,
                'office_id' => $management->office_id,
                'employee_id' => 'G4-AUD-'.$users->count(),
                'position' => 'Internal Auditor',
            ]);
            $user->syncRoleAssignments([$auditorRole->id], $auditorRole->id);
            $users->push($user->fresh(['role.permissions', 'roles.permissions', 'office']));
        }
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $users[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 4,
            ])->assertCreated();
        }

        return [$management, $engagement->fresh(), $users->all()];
    }

    private function newManagement(string $employeeId): User
    {
        $role = Role::query()->where('code', 'cias_management')->firstOrFail();
        $office = $this->user('departmenthead')->office;
        $user = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'employee_id' => $employeeId,
            'position' => 'CIAS Management',
        ]);
        $user->syncRoleAssignments([$role->id], $role->id);
        return $user->fresh(['role.permissions', 'roles.permissions', 'office']);
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)->firstOrFail();
    }
}
