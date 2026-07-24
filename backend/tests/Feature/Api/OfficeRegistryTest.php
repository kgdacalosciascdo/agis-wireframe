<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_authorized_demo_roles_can_view_offices(): void
    {
        foreach (['admin', 'agisadmin', 'departmenthead', 'auditor', 'mayor'] as $username) {
            Sanctum::actingAs($this->user($username));

            $this->getJson('/api/offices')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonCount(43, 'data.offices');
        }

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/offices')->assertForbidden();
    }

    public function test_administrator_can_manage_offices_and_changes_are_audited(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $created = $this->postJson('/api/offices', [
            'code' => 'test',
            'name' => 'Test Office',
            'acronym' => 'to',
            'description' => 'Created by the feature test.',
            'isActive' => true,
            'auditAreaIds' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('data.office.code', 'TEST')
            ->assertJsonPath('data.office.acronym', 'TO')
            ->json('data.office');

        $this->putJson("/api/offices/{$created['id']}", [
            'code' => 'TEST',
            'name' => 'Updated Test Office',
            'acronym' => 'UTO',
            'description' => 'Updated by the feature test.',
            'isActive' => false,
            'auditAreaIds' => [
                AuditArea::query()->where('code', 'PROCUREMENT')->value('id'),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.office.name', 'Updated Test Office')
            ->assertJsonPath('data.office.isActive', false)
            ->assertJsonCount(1, 'data.office.auditAreas');

        $this->deleteJson("/api/offices/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Office archived successfully.');

        $this->assertSoftDeleted('offices', ['id' => $created['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office.archived']);

        $this->getJson('/api/offices?include_archived=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $created['id'],
                'isArchived' => true,
            ]);

        $this->postJson("/api/offices/{$created['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.office.isArchived', false);
        $this->assertNotSoftDeleted('offices', ['id' => $created['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office.restored']);
    }

    public function test_department_head_and_auditor_cannot_mutate_offices(): void
    {
        foreach (['departmenthead', 'auditor', 'auditee', 'mayor'] as $username) {
            Sanctum::actingAs($this->user($username));
            $office = Office::query()->firstOrFail();

            $this->postJson('/api/offices', [
                'code' => "NO-{$username}",
                'name' => 'Not Allowed',
            ])->assertForbidden();

            $this->putJson("/api/offices/{$office->id}", [
                'code' => $office->code,
                'name' => 'Not Allowed',
            ])->assertForbidden();

            $this->deleteJson("/api/offices/{$office->id}")
                ->assertForbidden();
        }
    }

    public function test_administrator_can_reset_demo_data(): void
    {
        Sanctum::actingAs($this->user('admin'));

        Office::query()->create([
            'code' => 'CUSTOM',
            'name' => 'Custom Demo Office',
            'is_active' => true,
        ]);

        $seedOffice = Office::query()->where('code', 'CIAS')->firstOrFail();
        $seedOffice->delete();

        $this->user('auditor')->forceFill([
            'password' => 'temporarily-changed',
        ])->save();

        $this->postJson('/api/demo/reset')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(43, 'data.offices');

        $this->assertSoftDeleted('offices', ['code' => 'CUSTOM']);
        $this->assertDatabaseHas('offices', ['code' => 'CIAS', 'deleted_at' => null]);
        $this->assertTrue(Hash::check('lala', $this->user('auditor')->password));
        $this->assertTrue(
            AuditLog::query()->where('action', 'demo.reset')->exists(),
        );
    }

    public function test_non_administrator_cannot_reset_demo_data(): void
    {
        foreach (['departmenthead', 'auditor', 'auditee', 'mayor'] as $username) {
            Sanctum::actingAs($this->user($username));
            $this->postJson('/api/demo/reset')->assertForbidden();
        }
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
