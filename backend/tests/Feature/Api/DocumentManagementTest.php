<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\MasterList;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_authorized_user_can_manage_private_reference_documents(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));
        $documentTypeId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()
            ->items()
            ->where('code', 'INTERNAL_AUDIT_MANUAL')
            ->value('id');

        $this->post('/api/documents', [
            'documentTypeId' => $documentTypeId,
            'title' => 'Philippine Government Internal Audit Manual — Volume I',
            'referenceNumber' => 'PGIAM-VOL-1',
            'issuingAuthority' => 'Department of Budget and Management',
            'publicationDate' => '2020-01-15',
            'version' => 'Volume I',
            'description' => 'Reference manual for government internal auditing.',
            'isActive' => true,
            'file' => UploadedFile::fake()->create(
                'PGIAM-Volume-I.pdf',
                120,
                'application/pdf',
            ),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.document.documentType', 'Internal Audit Manual / PGIAM')
            ->assertJsonPath('data.document.referenceNumber', 'PGIAM-VOL-1')
            ->assertJsonPath('data.document.isArchived', false);

        $document = Document::query()->firstOrFail();
        Storage::disk('local')->assertExists($document->storage_path);

        $this->getJson('/api/documents?include_archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonCount(10, 'data.documentTypes')
            ->assertJsonPath('data.documents.0.uploadedBy', 'Kim V. Lao');

        $this->putJson("/api/documents/{$document->id}", [
            'documentTypeId' => $documentTypeId,
            'title' => 'Philippine Government Internal Audit Manual — Volume 1',
            'referenceNumber' => 'PGIAM-VOL-1',
            'issuingAuthority' => 'Department of Budget and Management',
            'publicationDate' => '2020-01-15',
            'version' => 'Revised metadata',
            'description' => 'Updated reference description.',
            'isActive' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.document.version', 'Revised metadata')
            ->assertJsonPath('data.document.fileName', 'PGIAM-Volume-I.pdf');

        $this->get("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->deleteJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Document archived successfully.');
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->storage_path);

        $this->postJson("/api/documents/{$document->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.document.isArchived', false);
        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'document.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.archived']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.restored']);
    }

    public function test_document_actions_are_protected_by_specific_permissions(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $documentTypeId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()
            ->items()
            ->where('code', 'LAW_STATUTE')
            ->value('id');
        $this->post('/api/documents', [
            'documentTypeId' => $documentTypeId,
            'title' => 'Reference Law',
            'isActive' => true,
            'file' => UploadedFile::fake()->create(
                'reference-law.pdf',
                50,
                'application/pdf',
            ),
        ], ['Accept' => 'application/json'])->assertCreated();
        $document = Document::query()->firstOrFail();

        Sanctum::actingAs($this->user('mayor'));

        $this->getJson('/api/documents')->assertOk();
        $this->postJson('/api/documents', [])->assertForbidden();
        $this->putJson("/api/documents/{$document->id}", [])->assertForbidden();
        $this->deleteJson("/api/documents/{$document->id}")->assertForbidden();
        $this->postJson("/api/documents/{$document->id}/restore")->assertForbidden();
        $this->getJson("/api/documents/{$document->id}/download")->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
