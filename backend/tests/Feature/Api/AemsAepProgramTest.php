<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlanVersion;
use App\Models\AuditProgram;
use App\Models\IapPlanEngagement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsAepProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_aep_requires_an_issued_aeo_and_preserves_immutable_risk_linked_versions(): void
    {
        [$management, $engagement, $team] = $this->preparedEngagement();
        $preparer = $team['TEAM_LEADER'];
        Sanctum::actingAs($preparer);

        $payload = $this->aepPayload($engagement);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aep", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('engagement');

        $this->issueAeo($management, $engagement, $team, $preparer);
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aep", $payload)
            ->assertCreated();
        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/aep")
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'DRAFT')
            ->json('data');
        $plan = $workspace['plan'];
        $this->assertSame(
            data_get($engagement->source_snapshot, 'riskAssessment.id'),
            data_get($plan, 'latestVersion.linkedRiskSnapshot.riskAssessment.id'),
        );

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $plan['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $plan['lockVersion'],
                'comment' => 'Attempted approval without independent review.',
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            [
                'action' => 'REVIEW',
                'lockVersion' => $plan['lockVersion'],
                'comment' => 'Objectives, scope, methodology, risks, and resources reviewed.',
            ],
        )->assertOk();
        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $plan['lockVersion'],
                'comment' => 'Approved as the engagement planning baseline.',
            ],
        )->assertOk()->assertJsonPath('data.plan.status', 'APPROVED');

        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/revise",
            [
                'lockVersion' => $plan['lockVersion'],
                'reason' => 'Management coordination and sampling requirements changed.',
            ],
        )->assertOk()->assertJsonPath('data.plan.status', 'DRAFT');
        $this->assertDatabaseCount('audit_engagement_plan_versions', 2);

        $version = AuditEngagementPlanVersion::query()->oldest('version_number')->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['scope' => 'An approved historical version cannot be overwritten.']);
    }

    public function test_audit_program_approval_creates_a_baseline_and_formal_revision_preserves_it(): void
    {
        [$management, $engagement, $team] = $this->preparedEngagement();
        $preparer = $team['TEAM_LEADER'];
        $this->issueAeo($management, $engagement, $team, $preparer);
        $this->approveAep($management, $engagement, $preparer);

        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/programs", [
            'title' => 'Revenue Collection Audit Program',
            'objective' => 'Determine whether collection, reconciliation, and deposit controls operate effectively.',
        ])->assertCreated();
        $program = $this->currentProgram($engagement);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures",
            [
                'programLockVersion' => $program['lockVersion'],
                'procedureCode' => 'RC-01',
                'sequenceNumber' => 1,
                'objective' => 'Test daily collection reconciliation.',
                'procedureDescription' => 'Select collection days and reconcile official receipts, reports, and deposits.',
                'expectedEvidence' => 'Official receipts, daily collection reports, and validated deposit slips.',
                'assignedTo' => $team['AUDITOR']->id,
                'targetDate' => '2026-08-14',
            ],
        )->assertCreated();
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $program['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            [
                'action' => 'REVIEW',
                'lockVersion' => $program['lockVersion'],
                'comment' => 'Procedures and expected evidence independently reviewed.',
            ],
        )->assertOk();
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $program['lockVersion'],
                'comment' => 'Approved as the fieldwork baseline.',
            ],
        )->assertOk()->assertJsonPath('data.program.status', 'APPROVED');

        $program = $this->currentProgram($engagement);
        $this->putJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}",
            [
                'title' => 'Attempted baseline overwrite',
                'objective' => $program['objective'],
                'lockVersion' => $program['lockVersion'],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            ['action' => 'START', 'lockVersion' => $program['lockVersion']],
        )->assertOk()->assertJsonPath('data.program.status', 'ACTIVE');

        Sanctum::actingAs($team['AUDITOR']);
        $program = $this->currentProgram($engagement);
        $procedure = $program['procedures'][0];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures/{$procedure['id']}/progress",
            [
                'programLockVersion' => $program['lockVersion'],
                'lockVersion' => $procedure['lockVersion'],
                'status' => 'COMPLETED',
                'workingPaperReference' => 'WP-RC-01',
                'comment' => 'Reconciliation test completed and cross-referenced.',
            ],
        )->assertOk()->assertJsonPath('data.procedure.status', 'COMPLETED');

        Sanctum::actingAs($team['REVIEWER']);
        $program = $this->currentProgram($engagement);
        $procedure = $program['procedures'][0];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures/{$procedure['id']}/review",
            [
                'programLockVersion' => $program['lockVersion'],
                'lockVersion' => $procedure['lockVersion'],
                'reviewerResult' => 'SATISFACTORY',
                'reviewerComments' => 'Evidence and working-paper reference are sufficient.',
            ],
        )->assertOk()->assertJsonPath('data.procedure.reviewer_result', 'SATISFACTORY');

        Sanctum::actingAs($management);
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/revise",
            [
                'lockVersion' => $program['lockVersion'],
                'reason' => 'Additional high-risk transactions require an expanded test procedure.',
            ],
        )->assertOk()
            ->assertJsonPath('data.program.status', 'DRAFT')
            ->assertJsonPath('data.program.revision_number', 1);

        $this->assertDatabaseHas('audit_programs', [
            'id' => $program['id'],
            'status' => 'SUPERSEDED',
            'is_current_revision' => false,
        ]);
        $this->assertDatabaseCount('audit_programs', 2);
        $this->assertDatabaseCount('audit_program_procedures', 2);
        $revision = AuditProgram::query()->where('is_current_revision', true)->firstOrFail();
        $this->assertSame(
            'Additional high-risk transactions require an expanded test procedure.',
            $revision->revision_reason,
        );
    }

    /** @return array{User, AuditEngagement, array<string, User>} */
    private function preparedEngagement(): array
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
        $engagement = AuditEngagement::query()->findOrFail($id);
        $users = $this->auditors(4);
        $team = [];
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $users[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated();
            $team[$role] = $users[$index];
        }

        return [$management, $engagement->fresh(), $team];
    }

    /** @param array<string, User> $team */
    private function issueAeo(
        User $management,
        AuditEngagement $engagement,
        array $team,
        User $preparer,
    ): void {
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", [
            'authority' => 'Authority is granted under the approved annual plan and the CIAS mandate.',
            'objectives' => 'Assess whether the audited controls operate effectively.',
            'scope' => 'Approved records, transactions, personnel, and systems.',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
        ])->assertCreated();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        foreach (['REVIEW', 'APPROVE', 'ISSUE'] as $action) {
            $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
                [
                    'action' => $action,
                    'lockVersion' => $order['lockVersion'],
                    'comment' => $action === 'REVIEW' ? 'Independent authorization review completed.' : null,
                ],
            )->assertOk();
        }
    }

    private function approveAep(
        User $management,
        AuditEngagement $engagement,
        User $preparer,
    ): void {
        Sanctum::actingAs($preparer);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep",
            $this->aepPayload($engagement),
        )->assertCreated();
        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $plan['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        foreach (['REVIEW', 'APPROVE'] as $action) {
            $plan = $this->aepWorkspace($engagement)['plan'];
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
                [
                    'action' => $action,
                    'lockVersion' => $plan['lockVersion'],
                    'comment' => $action === 'REVIEW' ? 'Independent AEP review completed.' : null,
                ],
            )->assertOk();
        }
    }

    /** @return array<string, mixed> */
    private function aepPayload(AuditEngagement $engagement): array
    {
        return [
            'objectives' => $engagement->objectives ?: 'Evaluate control design and operating effectiveness.',
            'scope' => $engagement->scope ?: 'Approved engagement scope.',
            'exclusions' => $engagement->exclusions,
            'methodology' => 'Inquiry, walkthrough, inspection, analytical review, and substantive testing.',
            'auditCriteria' => 'Applicable laws, policies, approved procedures, and internal-control standards.',
            'materiality' => 'Prioritize transactions and exceptions with significant financial or service exposure.',
            'samplingApproach' => 'Risk-based judgmental sampling supplemented by random selections.',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
            'expectedReportDate' => '2026-09-04',
            'plannedPersonDays' => 20,
            'resourceRequirements' => [
                'staffing' => 'Supervisor, Team Leader, Auditor, and independent Reviewer.',
                'skills' => 'Revenue operations and control testing.',
                'tools' => 'AGIS working papers and spreadsheet analysis.',
                'logistics' => 'Read-only source-record access and meeting room.',
            ],
            'managementCoordination' => [
                'contactPerson' => 'Office Head',
                'contactDetails' => 'Coordinate through the designated auditee representative.',
                'kickoffDetails' => 'Entrance conference before fieldwork.',
                'recordsDeadline' => '2026-08-05',
                'notes' => 'Escalate unavailable records to the Team Leader.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function aepWorkspace(AuditEngagement $engagement): array
    {
        return $this->getJson("/api/aems/engagements/{$engagement->id}/aep")
            ->assertOk()->json('data');
    }

    /** @return array<string, mixed> */
    private function currentProgram(AuditEngagement $engagement): array
    {
        $programs = $this->getJson("/api/aems/engagements/{$engagement->id}/programs")
            ->assertOk()->json('data.programs');

        return collect($programs)->firstWhere('isCurrentRevision', true);
    }

    /** @return list<User> */
    private function auditors(int $count): array
    {
        $users = User::query()
            ->whereHas('role', fn ($role) => $role->where('code', 'agis_user'))
            ->take($count)
            ->get();
        while ($users->count() < $count) {
            $users->push($this->newAuditor('CIAS-AEP-'.($users->count() + 100)));
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

    private function user(string $username): User
    {
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
