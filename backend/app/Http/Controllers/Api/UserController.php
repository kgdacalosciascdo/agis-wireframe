<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->with(['office:id,code,name', 'role:id,code,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->data($user));

        return response()->json(['success' => true, 'data' => ['users' => $users]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUser($request);
        $role = Role::query()->findOrFail($validated['roleId']);
        $this->ensureAssignable($request, $role);

        $user = User::query()->create([
            ...$this->attributes($validated),
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['isActive'] ?? true,
            'failed_login_attempts' => 0,
        ]);
        $this->syncPosition($user->position);

        ActivityRecorder::record(
            $request,
            'user.created',
            "{$request->user()->name} created the user account {$user->name}.",
            $user,
            null,
            $this->activityValues($user),
        );

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => ['user' => $this->data($user)],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);
        $validated = $this->validateUser($request, $user);
        $role = Role::query()->findOrFail($validated['roleId']);
        $this->ensureAssignable($request, $role);
        $oldValues = $this->activityValues($user);

        $user->update([
            ...$this->attributes($validated),
            'is_active' => $validated['isActive'] ?? $user->is_active,
        ]);
        $this->syncPosition($user->position);

        ActivityRecorder::record(
            $request,
            'user.updated',
            "{$request->user()->name} updated {$user->name}.",
            $user,
            $oldValues,
            $this->activityValues($user->fresh()),
        );

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => ['user' => $this->data($user)],
        ]);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your own account.'],
            ]);
        }

        $oldValues = ['isActive' => $user->is_active];
        $user->forceFill(['is_active' => false])->save();
        $user->delete();

        ActivityRecorder::record(
            $request,
            'user.archived',
            "{$request->user()->name} archived {$user->name}.",
            $user,
            $oldValues,
            ['isActive' => false, 'isArchived' => true],
        );

        return response()->json(['success' => true, 'message' => 'User archived successfully.']);
    }

    public function restore(Request $request, int $user): JsonResponse
    {
        $record = User::onlyTrashed()->with('role')->findOrFail($user);
        $this->ensureManageable($request, $record);

        if ($record->office_id && ! Office::query()->whereKey($record->office_id)->exists()) {
            throw ValidationException::withMessages([
                'office' => ['Restore the user’s assigned office before restoring this account.'],
            ]);
        }

        $record->restore();
        $record->forceFill(['is_active' => true])->save();

        ActivityRecorder::record(
            $request,
            'user.restored',
            "{$request->user()->name} restored {$record->name}.",
            $record,
            ['isActive' => false, 'isArchived' => true],
            ['isActive' => true, 'isArchived' => false],
        );

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully.',
            'data' => ['user' => $this->data($record)],
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->hasRole('platform_admin')) {
            abort(403, 'Only a Platform Administrator can reset user passwords.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:4', 'max:255', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => null,
            'lock_version' => $user->lock_version + 1,
        ])->save();
        $user->tokens()->delete();

        ActivityRecorder::record(
            $request,
            'user.password_reset',
            "{$request->user()->name} reset the password of {$user->name}.",
            $user,
            null,
            ['passwordReset' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'User password reset successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'employeeId' => ['required', 'string', 'max:40', Rule::unique('users', 'employee_id')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:255'],
            'employmentType' => ['nullable', 'string', 'max:80'],
            'contactNumber' => ['nullable', 'string', 'max:100'],
            'birthDate' => ['nullable', 'date', 'before:today'],
            'isOfficeHead' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
            'officeId' => ['required', 'integer', Rule::exists(Office::class, 'id')->whereNull('deleted_at')],
            'roleId' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:4', 'max:255'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $name = PersonName::fromParts(
            $validated['firstName'],
            $validated['middleName'] ?? null,
            $validated['lastName'],
            $validated['extension'] ?? null,
        );

        return [
            'username' => 'employee:'.strtolower(trim($validated['employeeId'])),
            'employee_id' => strtoupper(trim($validated['employeeId'])),
            'email' => $validated['email'] ?? null,
            ...$name,
            'position' => $validated['position'] ?? null,
            'employment_type' => $validated['employmentType'] ?? null,
            'contact_number' => $validated['contactNumber'] ?? null,
            'birth_date' => $validated['birthDate'] ?? null,
            'is_office_head' => $validated['isOfficeHead'] ?? false,
            'office_id' => $validated['officeId'],
            'role_id' => $validated['roleId'],
        ];
    }

    private function ensureAssignable(Request $request, Role $role): void
    {
        if ($role->code === 'platform_admin' && ! $request->user()->hasRole('platform_admin')) {
            abort(403, 'Only a Platform Administrator can assign that role.');
        }
    }

    private function syncPosition(?string $position): void
    {
        $position = trim((string) $position);

        if ($position === '') {
            return;
        }

        $list = MasterList::query()->where('code', 'POSITION')->first();

        if (! $list) {
            return;
        }

        $code = Str::of($position)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->limit(60, '')
            ->toString();

        $item = $list->items()->withTrashed()->firstOrNew(['code' => $code]);
        $item->fill([
            'label' => $position,
            'description' => $item->exists
                ? $item->description
                : 'Custom position title added from the User Registry.',
            'display_order' => $item->exists
                ? $item->display_order
                : ($list->items()->max('display_order') ?? 0) + 1,
            'is_active' => true,
        ])->save();

        if ($item->trashed()) {
            $item->restore();
        }
    }

    private function ensureManageable(Request $request, User $user): void
    {
        $user->loadMissing('role');
        if ($user->role?->code === 'platform_admin' && ! $request->user()->hasRole('platform_admin')) {
            abort(403, 'Only a Platform Administrator can manage that account.');
        }
    }

    /** @return array<string, mixed> */
    private function activityValues(User $user): array
    {
        $user->loadMissing(['office:id,name', 'role:id,name']);

        return [
            'employeeId' => $user->employee_id,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'extension' => $user->name_extension,
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'contactNumber' => $user->contact_number,
            'birthDate' => $user->birth_date?->toDateString(),
            'office' => $user->office?->name,
            'role' => $user->role?->name,
            'isOfficeHead' => $user->is_office_head,
            'isActive' => $user->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function data(User $user): array
    {
        $user->loadMissing(['office:id,code,name', 'role:id,code,name']);

        return [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'extension' => $user->name_extension,
            'name' => $user->name,
            'initials' => $user->initials,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'contactNumber' => $user->contact_number,
            'birthDate' => $user->birth_date?->toDateString(),
            'isOfficeHead' => $user->is_office_head,
            'isActive' => $user->is_active,
            'isArchived' => $user->trashed(),
            'officeId' => $user->office_id,
            'officeCode' => $user->office?->code,
            'office' => $user->office?->name,
            'roleId' => $user->role_id,
            'roleCode' => $user->role?->code,
            'role' => $user->role?->name,
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
