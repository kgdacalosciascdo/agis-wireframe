<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrderVersion;
use App\Models\IapPlanEngagement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsTeamAeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_team_assignment_reassignment_warnings_and_history_are_controlled(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);

        $roles = ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'];
        $assignments = collect($roles)->map(function (string $role, int $index) use ($engagement, $auditors) {
            return $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 4,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated()->json('data.teamMember');
        });

        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team")
            ->assertOk()
            ->assertJsonPath('data.summary.members', 4)
            ->assertJsonPath('data.summary.rolesFilled', 4)
            ->json('data');
        $this->assertNotEmpty($overview['warnings']);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditors[0]->id,
            'type' => 'AEMS_TEAM_ASSIGNED',
            'module_code' => 'AEMS',
        ]);

        $replacement = $this->newAuditor('CIAS-AUD-REPLACEMENT');
        $oldAuditor = $assignments->firstWhere('assignment_role_code', 'AUDITOR')
            ?? $assignments[2];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/team/{$oldAuditor['id']}/reassign",
            [
                'replacementUserId' => $replacement->id,
                'reason' => 'Reassigned because of an overlapping field assignment.',
            ],
        )->assertOk()
            ->assertJsonPath('data.teamMember.user_id', $replacement->id);

        $this->assertSoftDeleted('engagement_teams', ['id' => $oldAuditor['id']]);
        $this->assertDatabaseHas('engagement_team_history', [
            'audit_engagement_id' => $engagement->id,
            'action' => 'REASSIGNED_FROM',
        ]);
        $this->assertDatabaseHas('engagement_team_history', [
            'audit_engagement_id' => $engagement->id,
            'action' => 'REASSIGNED_TO',
        ]);
    }

    public function test_aeo_requires_independent_review_uses_immutable_versions_and_exports_approved_pdf(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated();
        }
        $preparer = $auditors[1];
        $issuer = $this->newManagement('CIAS-ISSUER-001');
        Sanctum::actingAs($preparer);
        $payload = [
            'authority' => 'Authority is granted under the approved annual plan and CIAS mandate.',
            'objectives' => 'Assess whether the audited controls are properly designed and operating.',
            'scope' => 'Transactions, records, personnel, and systems within the approved period.',
            'effectivityDate' => '2026-08-01',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
        ];
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", $payload)
            ->assertCreated();
        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertOk()->json('data');
        $order = $workspace['order'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'REVIEW', 'lockVersion' => $order['lockVersion'], 'comment' => 'Self review'],
        )->assertForbidden();

        Sanctum::actingAs($auditors[3]);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'REVIEW', 'lockVersion' => $order['lockVersion'], 'comment' => 'Reviewed against the approved IAP and current team.'],
        )->assertOk();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Approved for issuance.'],
        )->assertOk();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        Sanctum::actingAs($issuer);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued to the audit team and auditee office.'],
        )->assertOk();

        $pdf = $this->get("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $version = AuditEngagementOrderVersion::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['authority' => 'This must never overwrite the version.']);
    }

    public function test_formal_revision_preserves_the_issued_version_as_pdf_source(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
            ])->assertCreated();
        }
        $payload = [
            'authority' => 'Authority under the approved annual plan.',
            'objectives' => 'Review the engagement objectives and controls.',
            'scope' => 'Approved engagement scope and audit period.',
        ];
        $preparer = $auditors[1];
        $issuer = $this->newManagement('CIAS-ISSUER-002');
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", $payload)
            ->assertCreated();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($auditors[3]);
        foreach (['REVIEW'] as $action) {
            $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
                ->json('data.order');
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
                [
                    'action' => $action,
                    'lockVersion' => $order['lockVersion'],
                    'comment' => $action === 'REVIEW' ? 'Independent review completed.' : null,
                ],
            )->assertOk();
        }
        Sanctum::actingAs($management);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Independent approval completed.'],
        )->assertOk();
        Sanctum::actingAs($issuer);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued by a separate authority.'],
        )->assertOk();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/revise",
            ['lockVersion' => $order['lockVersion'], 'reason' => 'The authorized schedule and scope require revision.'],
        )->assertOk()
            ->assertJsonPath('data.order.status', 'DRAFT');

        $this->get("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('audit_engagement_order_versions', 2);
    }

    /** @return array{User, AuditEngagement} */
    private function engagement(): array
    {
        $management = $this->user('departmenthead');
        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE',
            'approved_at' => now()->subDay(),
            'approved_by' => $management->id,
            'activated_at' => now(),
            'activated_by' => $management->id,
        ]);
        Sanctum::actingAs($management);
        $id = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        return [$management, AuditEngagement::query()->findOrFail($id)];
    }

    /** @return list<User> */
    private function auditors(int $count): array
    {
        $users = User::query()
            ->whereHas('role', fn ($role) => $role->where('code', 'agis_user'))
            ->take($count)
            ->get();
        while ($users->count() < $count) {
            $users->push($this->newAuditor('CIAS-AUD-'.($users->count() + 100)));
        }

        return $users->take($count)->values()->all();
    }

    private function newAuditor(string $employeeId): User
    {
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $office = $this->user('auditor')->office;
        $user = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'employee_id' => $employeeId,
            'position' => 'Internal Auditor',
        ]);
        $user->syncRoleAssignments([$role->id], $role->id);

        return $user->fresh(['role.permissions', 'roles.permissions', 'office']);
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
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
