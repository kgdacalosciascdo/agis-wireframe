<?php

namespace Tests\Feature\Api;

use App\Models\AemsTeamSafeguardAssessment;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\ArmisProviderAuthorityDecision;
use App\Models\ArmisProviderReconciliationReview;
use App\Models\ArmisProviderReconciliationRun;
use App\Models\AuditEngagement;
use App\Models\IapPlanEngagement;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsTeamSafeguardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_declarations_require_independent_review_and_approved_assessment_is_immutable(): void
    {
        [$management, $engagement, $team] = $this->engagementWithTeam();
        $requiredDays = max(1, (float) $engagement->planned_person_days / 4);

        foreach ($team as $member) {
            Sanctum::actingAs($member->user);
            $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
                'declarationType' => 'OBJECTIVITY',
                'outcome' => 'CLEAR',
                'statement' => 'I have assessed my objectivity and have no disqualifying matter.',
            ])->assertCreated();
            foreach (['CONFLICT_OF_INTEREST', 'INDEPENDENCE'] as $type) {
                $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
                    'declarationType' => $type,
                    'outcome' => 'CLEAR',
                    'statement' => "I confirm my {$type} declaration for this engagement.",
                ])->assertCreated();
            }
        }

        Sanctum::actingAs($management);
        foreach (AemsTeamSafeguardDeclaration::query()->get() as $declaration) {
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/team/{$declaration->engagement_team_id}/safeguards/declarations/{$declaration->id}/review",
                ['decision' => 'ACCEPT', 'reviewNotes' => 'Independently reviewed and accepted.'],
            )->assertOk();
        }

        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team/safeguards")
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'IAP_INTERIM_FALLBACK')
            ->json('data');
        $this->assertTrue($overview['approvalReady']);
        $this->assertSame($requiredDays * 4, (float) $overview['reconciliation']['planned']['team']);

        Sanctum::actingAs($team[0]->user);
        $assessment = $this->postJson("/api/aems/engagements/{$engagement->id}/team/safeguards/assess")
            ->assertCreated()
            ->json('data.assessment');
        Sanctum::actingAs($management);
        $approved = $this->postJson("/api/aems/engagements/{$engagement->id}/team/safeguards/approve", [
            'comment' => 'Approved after independent review of provider and safeguards.',
        ])->assertOk()->json('data.assessment');
        $this->assertSame('APPROVED', $approved['status']);
        $this->assertDatabaseHas('aems_team_safeguard_assessments', [
            'id' => $approved['id'],
            'status' => 'APPROVED',
            'is_current_revision' => true,
        ]);

        $this->expectException(LogicException::class);
        AemsTeamSafeguardAssessment::query()->findOrFail($approved['id'])->update(['decision_comment' => 'overwrite']);
    }

    public function test_accepted_declaration_correction_creates_a_new_revision(): void
    {
        [$management, $engagement, $team] = $this->engagementWithTeam();
        $member = $team[0];
        Sanctum::actingAs($member->user);
        $first = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'My objectivity declaration is complete and accurate.',
        ])->assertCreated()->json('data.declaration');
        Sanctum::actingAs($management);
        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations/{$first['id']}/review", [
            'decision' => 'ACCEPT',
            'reviewNotes' => 'Accepted after independent review.',
        ])->assertOk();

        Sanctum::actingAs($member->user);
        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'Correction is submitted as a new immutable declaration version.',
        ])->assertStatus(422);
        $revision = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'Correction is submitted as a new immutable declaration version.',
            'revisionReason' => 'Corrected the statement to reflect the current assignment.',
        ])->assertCreated()->json('data.declaration');
        $this->assertSame(2, $revision['version_number'] ?? $revision['versionNumber']);
        $this->assertDatabaseHas('aems_team_safeguard_declarations', [
            'id' => $first['id'],
            'is_current_revision' => false,
            'status' => 'ACCEPTED',
        ]);
    }

    public function test_authoritative_armis_mode_blocks_missing_resource_data(): void
    {
        [$management, $engagement] = $this->engagementWithTeam();
        $run = ArmisProviderReconciliationRun::query()->create([
            'run_uuid' => (string) str()->uuid(),
            'source_query_version' => 'ARMIS-6B-v1',
            'fiscal_year' => 2026,
            'provider_mode' => 'ARMIS_SHADOW',
            'status' => 'GENERATED',
            'filters' => ['fiscalYear' => 2026],
            'scope_snapshot' => ['officeIds' => [$management->office_id]],
            'result_snapshot' => [],
            'summary' => ['reviewRequired' => false],
            'result_checksum_sha256' => hash('sha256', 'aems-3a-test'),
            'generated_by' => $management->id,
            'generated_at' => now(),
        ]);
        ArmisProviderReconciliationReview::query()->create([
            'reconciliation_run_id' => $run->id,
            'decision' => 'ACCEPTED',
            'discrepancy_decisions' => [],
            'comment' => 'Accepted for provider authority test.',
            'reviewed_by' => $management->id,
            'reviewed_at' => now(),
        ]);
        ArmisProviderAuthorityDecision::query()->create([
            'reconciliation_run_id' => $run->id,
            'decision_code' => 'ACTIVATE_AUTHORITY',
            'from_mode' => 'ARMIS_SHADOW',
            'to_mode' => 'ARMIS_AUTHORITATIVE',
            'reason' => 'Authority gate test.',
            'decided_by' => $management->id,
            'decided_at' => now(),
        ]);
        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->firstOrFail();
        $configuration->value = 'ARMIS_AUTHORITATIVE';
        $configuration->save();
        app(\App\Services\RuntimeConfiguration::class)->forget();

        Sanctum::actingAs($management);
        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team/safeguards")
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE')
            ->json('data');
        $this->assertFalse($overview['approvalReady']);
        $this->assertNotEmpty(collect($overview['blockers'])->where('code', 'ARMIS_PROFILE_MISSING')->all());
    }

    public function test_requested_authority_without_decision_fails_closed(): void
    {
        [$management, $engagement] = $this->engagementWithTeam();
        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->firstOrFail();
        $configuration->value = 'ARMIS_AUTHORITATIVE';
        $configuration->save();
        app(\App\Services\RuntimeConfiguration::class)->forget();

        Sanctum::actingAs($management);
        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team/safeguards")
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'IAP_INTERIM_FALLBACK')
            ->json('data');
        $this->assertFalse($overview['approvalReady']);
        $this->assertNotEmpty(collect($overview['blockers'])->where('code', 'RESOURCE_AUTHORITY_NOT_ACTIVE')->all());
    }

    /** @return array{User, AuditEngagement, list<\App\Models\EngagementTeam>} */
    private function engagementWithTeam(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE',
            'approved_at' => now()->subDay(),
            'approved_by' => $management->id,
            'activated_at' => now(),
            'activated_by' => $management->id,
        ]);
        Sanctum::actingAs($management);
        $id = $this->postJson('/api/aems/engagements/import', ['iapPlanEngagementId' => $source->id])
            ->assertCreated()->json('data.engagement.id');
        $engagement = AuditEngagement::query()->findOrFail($id);
        $users = User::query()->whereHas('role', fn ($role) => $role->where('code', 'agis_user'))->take(4)->get();
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $officeId = $management->office_id;
        while ($users->count() < 4) {
            $user = User::factory()->create([
                'role_id' => $role->id,
                'office_id' => $officeId,
                'employee_id' => 'AEMS-SG-'.($users->count() + 1),
                'position' => 'Internal Auditor',
            ]);
            $user->syncRoleAssignments([$role->id], $role->id);
            $users->push($user->fresh(['role.permissions', 'roles.permissions']));
        }
        $roles = ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'];
        $team = [];
        foreach ($roles as $index => $roleCode) {
            Sanctum::actingAs($management);
            $memberId = $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $users[$index]->id,
                'assignmentRoleCode' => $roleCode,
                'plannedPersonDays' => max(1, (float) $engagement->planned_person_days / 4),
            ])->assertCreated()->json('data.teamMember.id');
            $team[] = $engagement->teamMembers()->findOrFail($memberId)->load('user');
        }

        return [$management, $engagement->fresh(), $team];
    }
}
