<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class CoreUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.default_password');

        if (! is_string($password) || $password === '') {
            throw new LogicException('DEMO_DEFAULT_PASSWORD is required before seeding Core users.');
        }

        $auditeeRole = Role::query()->where('code', 'auditee_representative')->firstOrFail();
        $agisUserRole = Role::query()->where('code', 'agis_user')->firstOrFail();

        foreach (OfficeSeeder::DEMO_OFFICES as $officeData) {
            if ($officeData['code'] === 'AGIS-SYS') {
                continue;
            }

            $office = Office::query()->where('code', $officeData['code'])->firstOrFail();
            $usernamePrefix = Str::of($officeData['code'])->lower()->replace('-', '_')->toString();

            if ($officeData['code'] === 'CIAS') {
                $head = User::query()->where('username', 'departmenthead')->firstOrFail();
                $head->forceFill([
                    'employee_id' => 'CIAS-HEAD-001',
                    'position' => 'City Internal Audit Officer',
                    'employment_type' => 'Permanent',
                    'contact_number' => $officeData['contact_number'],
                    'is_office_head' => true,
                ])->save();

                $auditor = User::query()->where('username', 'auditor')->firstOrFail();
                $auditor->forceFill([
                    'employee_id' => 'CIAS-AUD-001',
                    'position' => 'Internal Auditor',
                    'employment_type' => 'Permanent',
                    'is_office_head' => false,
                ])->save();

                $this->upsertUser(
                    "{$usernamePrefix}.employee",
                    'CIAS Demo Auditor',
                    $office,
                    $agisUserRole,
                    'Internal Auditor',
                    false,
                    $password,
                );

                continue;
            }

            $this->upsertUser(
                "{$usernamePrefix}.head",
                $officeData['head_name'],
                $office,
                $auditeeRole,
                'Office Head',
                true,
                $password,
            );
            $this->upsertUser(
                "{$usernamePrefix}.employee",
                "{$officeData['acronym']} Demo Employee",
                $office,
                $auditeeRole,
                'Office Employee',
                false,
                $password,
            );
        }
    }

    private function upsertUser(
        string $username,
        string $name,
        Office $office,
        Role $role,
        string $position,
        bool $isOfficeHead,
        string $password,
    ): void {
        $normalizedName = PersonName::parse($name);

        $user = User::withTrashed()->updateOrCreate(
            ['username' => $username],
            [
                'role_id' => $role->id,
                'office_id' => $office->id,
                'employee_id' => Str::upper(str_replace('.', '-', $username)),
                'email' => str_replace('.', '-', $username).'@agis.local',
                ...$normalizedName,
                'position' => $position,
                'employment_type' => 'Permanent',
                'password' => Hash::make($password),
                'is_office_head' => $isOfficeHead,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'lock_version' => 0,
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }
    }
}
