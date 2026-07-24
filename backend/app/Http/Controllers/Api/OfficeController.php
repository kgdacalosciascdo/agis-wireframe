<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfficeRequest;
use App\Http\Resources\OfficeResource;
use App\Models\AuditLog;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $offices = Office::query()
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->with([
                'head:id,office_id,name',
                'auditAreas:id,code,name,description',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('acronym', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['offices' => OfficeResource::collection($offices)],
        ]);
    }

    public function store(OfficeRequest $request): JsonResponse
    {
        $office = DB::transaction(function () use ($request): Office {
            $attributes = $this->attributes($request);
            $office = Office::withTrashed()
                ->where('code', $attributes['code'])
                ->lockForUpdate()
                ->first();

            if ($office?->trashed()) {
                $office->fill($attributes);
                $office->restore();
                $office->save();
            } else {
                $office = Office::query()->create($attributes);
            }

            $office->auditAreas()->sync($request->validated('auditAreaIds', []));
            $this->record($request, 'office.created', $office, null, $office->toArray());

            return $office->load(['head', 'auditAreas']);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Office created successfully.',
            'data' => ['office' => new OfficeResource($office)],
        ], 201);
    }

    public function update(OfficeRequest $request, Office $office): JsonResponse
    {
        $oldValues = [
            ...$office->only(['code', 'name', 'acronym', 'description', 'is_active']),
            'audit_area_ids' => $office->auditAreas()->pluck('audit_areas.id')->all(),
        ];

        DB::transaction(function () use ($request, $office): void {
            $office->update($this->attributes($request, $office));
            $office->auditAreas()->sync($request->validated('auditAreaIds', []));
        }, 3);

        $newValues = [
            ...$office->only(['code', 'name', 'acronym', 'description', 'is_active']),
            'audit_area_ids' => $office->auditAreas()->pluck('audit_areas.id')->all(),
        ];

        $this->record($request, 'office.updated', $office, $oldValues, $newValues);

        return response()->json([
            'success' => true,
            'message' => 'Office updated successfully.',
            'data' => ['office' => new OfficeResource($office->fresh(['head', 'auditAreas']))],
        ]);
    }

    public function destroy(Request $request, Office $office): JsonResponse
    {
        if ($office->users()->exists()) {
            throw ValidationException::withMessages([
                'office' => ['Reassign or archive this office’s active users before archiving the office.'],
            ]);
        }

        $oldValues = $office->only(['code', 'name', 'acronym', 'description', 'is_active']);

        DB::transaction(function () use ($request, $office, $oldValues): void {
            $office->forceFill(['is_active' => false])->save();
            $this->record($request, 'office.archived', $office, $oldValues, ['is_active' => false]);
            $office->delete();
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Office archived successfully.',
        ]);
    }

    public function restore(Request $request, int $office): JsonResponse
    {
        $record = Office::onlyTrashed()->findOrFail($office);

        DB::transaction(function () use ($request, $record): void {
            $record->restore();
            $record->forceFill(['is_active' => true])->save();
            $this->record(
                $request,
                'office.restored',
                $record,
                ['is_active' => false, 'is_archived' => true],
                ['is_active' => true, 'is_archived' => false],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Office restored successfully.',
            'data' => [
                'office' => new OfficeResource($record->fresh(['head', 'auditAreas'])),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(OfficeRequest $request, ?Office $office = null): array
    {
        $validated = $request->validated();

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'acronym' => $validated['acronym'] ?? null,
            'description' => $validated['description'] ?? null,
            'sector' => $validated['sector'] ?? null,
            'contact_number' => $validated['contactNumber'] ?? null,
            'is_active' => $validated['isActive'] ?? $office?->is_active ?? true,
        ];
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(
        Request $request,
        string $action,
        Office $office,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => Office::class,
            'auditable_id' => $office->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
