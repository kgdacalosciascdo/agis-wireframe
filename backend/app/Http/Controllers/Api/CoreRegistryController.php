<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoreRegistryController extends Controller
{
    public function auditAreas(Request $request): JsonResponse
    {
        $includeArchived = $request->boolean('include_archived');
        $areas = AuditArea::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->with([
                'offices:id,code,name',
                'focuses' => fn ($query) => $query
                    ->when($includeArchived, fn ($query) => $query->withTrashed())
                    ->select(['id', 'audit_area_id', 'code', 'name', 'description', 'display_order', 'is_active', 'deleted_at']),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (AuditArea $area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'description' => $area->description,
                'isActive' => $area->is_active,
                'isArchived' => $area->trashed(),
                'offices' => $area->offices->map(fn (Office $office): array => [
                    'id' => $office->id,
                    'code' => $office->code,
                    'name' => $office->name,
                ])->values(),
                'focuses' => $area->focuses->map(fn (AuditFocus $focus): array => $this->focusData($focus))->values(),
            ]);

        return $this->success(['auditAreas' => $areas]);
    }

    public function storeAuditArea(Request $request): JsonResponse
    {
        $validated = $this->validateAuditArea($request);

        $area = DB::transaction(function () use ($validated): AuditArea {
            $area = AuditArea::query()->create([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'] ?? true,
            ]);
            $area->offices()->sync($validated['officeIds'] ?? []);

            return $area;
        });

        return $this->success(['auditArea' => $this->areaData($area)], 'Audit area created successfully.', 201);
    }

    public function updateAuditArea(Request $request, AuditArea $auditArea): JsonResponse
    {
        $validated = $this->validateAuditArea($request, $auditArea);

        DB::transaction(function () use ($validated, $auditArea): void {
            $auditArea->update([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'] ?? $auditArea->is_active,
            ]);
            $auditArea->offices()->sync($validated['officeIds'] ?? []);
        });

        return $this->success(['auditArea' => $this->areaData($auditArea)], 'Audit area updated successfully.');
    }

    public function destroyAuditArea(AuditArea $auditArea): JsonResponse
    {
        DB::transaction(function () use ($auditArea): void {
            $auditArea->focuses()->update(['is_active' => false]);
            $auditArea->focuses()->delete();
            $auditArea->forceFill(['is_active' => false])->save();
            $auditArea->delete();
        });

        return $this->success(message: 'Audit area archived successfully.');
    }

    public function restoreAuditArea(int $auditArea): JsonResponse
    {
        $area = AuditArea::onlyTrashed()->findOrFail($auditArea);

        DB::transaction(function () use ($area): void {
            $area->restore();
            $area->forceFill(['is_active' => true])->save();
            $area->focuses()->onlyTrashed()->restore();
            $area->focuses()->update(['is_active' => true]);
        });

        return $this->success(
            ['auditArea' => $this->areaData($area)],
            'Audit area restored successfully.',
        );
    }

    public function auditFocuses(Request $request): JsonResponse
    {
        $includeArchived = $request->boolean('include_archived');
        $focuses = AuditFocus::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->with([
                'auditArea' => fn ($query) => $query
                    ->when($includeArchived, fn ($query) => $query->withTrashed())
                    ->select(['id', 'code', 'name']),
            ])
            ->orderBy('audit_area_id')
            ->orderBy('display_order')
            ->get()
            ->map(fn (AuditFocus $focus): array => $this->focusData($focus, true));

        return $this->success(['auditFocuses' => $focuses]);
    }

    public function storeAuditFocus(Request $request): JsonResponse
    {
        $validated = $this->validateAuditFocus($request);
        $focus = AuditFocus::query()->create($this->focusAttributes($validated));

        return $this->success(['auditFocus' => $this->focusData($focus, true)], 'Audit focus created successfully.', 201);
    }

    public function updateAuditFocus(Request $request, AuditFocus $auditFocus): JsonResponse
    {
        $validated = $this->validateAuditFocus($request, $auditFocus);
        $auditFocus->update($this->focusAttributes($validated));

        return $this->success(['auditFocus' => $this->focusData($auditFocus, true)], 'Audit focus updated successfully.');
    }

    public function destroyAuditFocus(AuditFocus $auditFocus): JsonResponse
    {
        $auditFocus->forceFill(['is_active' => false])->save();
        $auditFocus->delete();

        return $this->success(message: 'Audit focus archived successfully.');
    }

    public function restoreAuditFocus(int $auditFocus): JsonResponse
    {
        $focus = AuditFocus::onlyTrashed()->findOrFail($auditFocus);
        $focus->restore();
        $focus->forceFill(['is_active' => true])->save();

        return $this->success(
            ['auditFocus' => $this->focusData($focus, true)],
            'Audit focus restored successfully.',
        );
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->with(['permissions:id,code,name,module,action'])
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
                'isSystem' => $role->is_system,
                'isActive' => $role->is_active,
                'usersCount' => $role->users_count,
                'permissionIds' => $role->permissions->pluck('id')->sort()->values(),
                'permissions' => $role->permissions->pluck('code')->sort()->values(),
            ]);

        return $this->success(['roles' => $roles]);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        if ($role->code === 'platform_admin' && ! $request->user()->hasRole('platform_admin')) {
            abort(403, 'Only a Platform Administrator can manage that role.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'permissionIds' => ['required', 'array'],
            'permissionIds.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        DB::transaction(function () use ($role, $validated): void {
            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'],
            ]);
            $role->permissions()->sync($validated['permissionIds']);
        });

        return $this->success(message: 'Access role updated successfully.');
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->get(['id', 'code', 'name', 'module', 'action', 'description']);

        return $this->success(['permissions' => $permissions]);
    }

    public function masterLists(): JsonResponse
    {
        $lists = MasterList::query()
            ->with('items')
            ->orderBy('name')
            ->get()
            ->map(fn (MasterList $list): array => $this->masterListData($list));

        return $this->success(['masterLists' => $lists]);
    }

    public function updateMasterList(Request $request, MasterList $masterList): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.code' => ['required', 'string', 'max:60'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.isActive' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($masterList, $validated): void {
            $masterList->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'],
            ]);

            $keptIds = [];
            foreach ($validated['items'] as $index => $item) {
                $saved = $masterList->items()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'code' => strtoupper($item['code']),
                        'label' => $item['label'],
                        'description' => $item['description'] ?? null,
                        'display_order' => $index + 1,
                        'is_active' => $item['isActive'],
                    ],
                );
                $keptIds[] = $saved->id;
            }

            $masterList->items()->whereNotIn('id', $keptIds)->delete();
        });

        return $this->success(message: 'Master list updated successfully.');
    }

    public function storeMasterList(Request $request): JsonResponse
    {
        $request->merge([
            'code' => $this->normalizeCode((string) $request->input('code')),
            'items' => collect($request->input('items', []))
                ->map(fn (array $item): array => [
                    ...$item,
                    'code' => $this->normalizeCode((string) ($item['code'] ?? '')),
                ])
                ->all(),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('master_lists', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.isActive' => ['required', 'boolean'],
        ]);

        $masterList = DB::transaction(function () use ($validated): MasterList {
            $list = MasterList::query()->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['isActive'],
            ]);

            foreach ($validated['items'] as $index => $item) {
                $list->items()->create([
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'description' => $item['description'] ?? null,
                    'display_order' => $index + 1,
                    'is_active' => $item['isActive'],
                ]);
            }

            return $list->load('items');
        });

        return $this->success(
            ['masterList' => $this->masterListData($masterList)],
            'Master list created successfully.',
            201,
        );
    }

    public function configurations(): JsonResponse
    {
        $configurations = SystemConfiguration::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(fn (SystemConfiguration $configuration): array => [
                'id' => $configuration->id,
                'key' => $configuration->key,
                'name' => $configuration->name,
                'value' => $configuration->value,
                'type' => $configuration->type,
                'group' => $configuration->group,
                'description' => $configuration->description,
            ]);

        return $this->success(['configurations' => $configurations]);
    }

    public function updateConfigurations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configurations' => ['required', 'array', 'min:1'],
            'configurations.*.key' => ['required', 'string', Rule::exists('system_configurations', 'key')],
            'configurations.*.value' => ['present'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated['configurations'] as $item) {
                $configuration = SystemConfiguration::query()->where('key', $item['key'])->firstOrFail();
                $value = match ($configuration->type) {
                    'integer' => (int) $item['value'],
                    'boolean' => filter_var($item['value'], FILTER_VALIDATE_BOOL),
                    default => (string) $item['value'],
                };
                $configuration->update(['value' => $value, 'updated_by' => $request->user()->id]);
            }
        });

        return $this->success(message: 'System configuration updated successfully.');
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $logs = ActivityLog::query()
            ->with(['user:id,name,initials', 'subjectUser:id,name'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest()
            ->limit(250)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'actor' => $log->user?->name ?? 'System',
                'actorInitials' => $log->user?->initials ?? 'SY',
                'subject' => $log->subjectUser?->name,
                'oldValues' => $log->old_values,
                'newValues' => $log->new_values,
                'ipAddress' => $log->ip_address,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);

        return $this->success(['activityLogs' => $logs]);
    }

    /** @return array<string, mixed> */
    private function validateAuditArea(Request $request, ?AuditArea $area = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('audit_areas', 'code')->ignore($area?->id)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['sometimes', 'boolean'],
            'officeIds' => ['required', 'array', 'min:1'],
            'officeIds.*' => ['integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateAuditFocus(Request $request, ?AuditFocus $focus = null): array
    {
        return $request->validate([
            'auditAreaId' => ['required', 'integer', Rule::exists('audit_areas', 'id')->whereNull('deleted_at')],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('audit_focuses', 'code')
                    ->where(fn ($query) => $query->where('audit_area_id', $request->integer('auditAreaId'))->whereNull('deleted_at'))
                    ->ignore($focus?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'displayOrder' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function focusAttributes(array $validated): array
    {
        return [
            'audit_area_id' => $validated['auditAreaId'],
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'display_order' => $validated['displayOrder'] ?? 0,
            'is_active' => $validated['isActive'] ?? true,
        ];
    }

    /** @return array<string, mixed> */
    private function focusData(AuditFocus $focus, bool $includeArea = false): array
    {
        $focus->loadMissing('auditArea:id,code,name');

        return [
            'id' => $focus->id,
            'auditAreaId' => $focus->audit_area_id,
            'auditAreaCode' => $includeArea ? $focus->auditArea?->code : null,
            'auditAreaName' => $includeArea ? $focus->auditArea?->name : null,
            'code' => $focus->code,
            'name' => $focus->name,
            'description' => $focus->description,
            'displayOrder' => $focus->display_order,
            'isActive' => $focus->is_active,
            'isArchived' => $focus->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function areaData(AuditArea $area): array
    {
        $area->load(['offices:id,code,name', 'focuses']);

        return [
            'id' => $area->id,
            'code' => $area->code,
            'name' => $area->name,
            'description' => $area->description,
            'isActive' => $area->is_active,
            'isArchived' => $area->trashed(),
            'offices' => $area->offices,
            'focuses' => $area->focuses->map(fn (AuditFocus $focus): array => $this->focusData($focus)),
        ];
    }

    private function success(array $data = [], ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?: null,
        ], $status);
    }

    /** @return array<string, mixed> */
    private function masterListData(MasterList $list): array
    {
        $list->loadMissing('items');

        return [
            'id' => $list->id,
            'code' => $list->code,
            'name' => $list->name,
            'description' => $list->description,
            'isActive' => $list->is_active,
            'items' => $list->items->map(fn ($item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'description' => $item->description,
                'displayOrder' => $item->display_order,
                'isActive' => $item->is_active,
            ])->values(),
        ];
    }

    private function normalizeCode(string $code): string
    {
        return Str::of($code)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
