<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\MasterList;
use App\Models\MasterListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->where('library_visible', true)
            ->with(['documentType', 'uploader:id,name', 'updater:id,name'])
            ->latest('updated_at')
            ->get()
            ->map(fn (Document $document): array => $this->data($document));

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents,
                'documentTypes' => $this->documentTypes(),
            ],
        ]);
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $storedFile = $this->storeFile($request->file('file'));

        try {
            $document = DB::transaction(function () use ($request, $storedFile): Document {
                $document = Document::query()->create([
                    ...$this->attributes($request),
                    ...$storedFile,
                    'owner_module' => null,
                    'library_visible' => true,
                    'uploaded_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $this->record($request, 'document.created', $document, null, $this->auditValues($document));

                return $document;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($storedFile['storage_path']);
            throw $error;
        }

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => ['document' => $this->data($document->load(['documentType', 'uploader', 'updater']))],
        ], 201);
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        $oldValues = $this->auditValues($document);
        $oldPath = $document->storage_path;
        $storedFile = $request->hasFile('file')
            ? $this->storeFile($request->file('file'))
            : [];

        try {
            DB::transaction(function () use ($request, $document, $storedFile, $oldValues): void {
                $document->update([
                    ...$this->attributes($request, $document),
                    ...$storedFile,
                    'updated_by' => $request->user()->id,
                ]);
                $this->record(
                    $request,
                    'document.updated',
                    $document,
                    $oldValues,
                    $this->auditValues($document),
                );
            }, 3);
        } catch (\Throwable $error) {
            if (isset($storedFile['storage_path'])) {
                Storage::disk('local')->delete($storedFile['storage_path']);
            }
            throw $error;
        }

        if (isset($storedFile['storage_path']) && $oldPath !== $storedFile['storage_path']) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully.',
            'data' => ['document' => $this->data($document->fresh(['documentType', 'uploader', 'updater']))],
        ]);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $oldValues = $this->auditValues($document);
        $document->forceFill([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ])->save();
        $document->delete();
        $this->record(
            $request,
            'document.archived',
            $document,
            $oldValues,
            $this->auditValues($document),
        );

        return response()->json([
            'success' => true,
            'message' => 'Document archived successfully.',
        ]);
    }

    public function restore(Request $request, int $document): JsonResponse
    {
        $record = Document::onlyTrashed()->findOrFail($document);

        if (! Storage::disk('local')->exists($record->storage_path)) {
            throw ValidationException::withMessages([
                'document' => ['The stored file is missing and this document cannot be restored.'],
            ]);
        }

        $record->restore();
        $record->forceFill([
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ])->save();
        $this->record(
            $request,
            'document.restored',
            $record,
            ['isActive' => false, 'isArchived' => true],
            $this->auditValues($record),
        );

        return response()->json([
            'success' => true,
            'message' => 'Document restored successfully.',
            'data' => ['document' => $this->data($record->load(['documentType', 'uploader', 'updater']))],
        ]);
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404, 'Stored file not found.');

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_file_name,
            ['Content-Type' => $document->mime_type],
        );
    }

    /** @return array<string, mixed> */
    private function attributes(DocumentRequest $request, ?Document $document = null): array
    {
        $validated = $request->validated();

        return [
            'document_type_id' => $validated['documentTypeId'],
            'title' => $validated['title'],
            'reference_number' => $validated['referenceNumber'] ?? null,
            'issuing_authority' => $validated['issuingAuthority'] ?? null,
            'publication_date' => $validated['publicationDate'] ?? null,
            'version' => $validated['version'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['isActive'] ?? $document?->is_active ?? true,
        ];
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs('documents', $file, $storedName);

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The document could not be stored. Please try again.'],
            ]);
        }

        return [
            'original_file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_extension' => $extension ?: null,
            'file_size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    /** @return array<string, mixed> */
    private function data(Document $document): array
    {
        return [
            'id' => $document->id,
            'documentTypeId' => $document->document_type_id,
            'documentType' => $document->documentType?->label ?? 'Unclassified',
            'documentTypeCode' => $document->documentType?->code,
            'title' => $document->title,
            'referenceNumber' => $document->reference_number,
            'issuingAuthority' => $document->issuing_authority,
            'publicationDate' => $document->publication_date?->toDateString(),
            'version' => $document->version,
            'description' => $document->description,
            'fileName' => $document->original_file_name,
            'fileExtension' => $document->file_extension,
            'fileSize' => $document->file_size,
            'mimeType' => $document->mime_type,
            'uploadedBy' => $document->uploader?->name ?? 'System',
            'updatedBy' => $document->updater?->name ?? 'System',
            'createdAt' => $document->created_at?->toIso8601String(),
            'updatedAt' => $document->updated_at?->toIso8601String(),
            'isActive' => $document->is_active,
            'isArchived' => $document->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(Document $document): array
    {
        return [
            'documentTypeId' => $document->document_type_id,
            'title' => $document->title,
            'referenceNumber' => $document->reference_number,
            'issuingAuthority' => $document->issuing_authority,
            'publicationDate' => $document->publication_date?->toDateString(),
            'version' => $document->version,
            'fileName' => $document->original_file_name,
            'fileSize' => $document->file_size,
            'isActive' => $document->is_active,
            'isArchived' => $document->trashed(),
        ];
    }

    private function record(
        Request $request,
        string $action,
        Document $document,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function documentTypes()
    {
        $listId = MasterList::query()->where('code', 'DOCUMENT_TYPE')->value('id');

        return MasterListItem::query()
            ->where('master_list_id', $listId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description']);
    }
}
