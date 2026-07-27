<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * The permission catalogue uses stable module.action codes so API policies and
     * the frontend can check a specific operation instead of relying on role names.
     *
     * @var array<string, list<string>>
     */
    private array $permissionCatalogue = [
        'dashboard' => ['view'],
        'offices' => ['view', 'create', 'update', 'delete', 'restore'],
        'audit_areas' => ['view', 'create', 'update', 'delete', 'restore'],
        'audit_focus' => ['view', 'create', 'update', 'delete', 'restore'],
        'users' => ['view', 'create', 'update', 'deactivate', 'restore', 'reset_password'],
        'roles' => ['view', 'create', 'update', 'delete', 'restore'],
        'permissions' => ['view', 'update'],
        'master_lists' => ['view', 'manage'],
        'iap' => [
            'view',
            'manage_universe',
            'create',
            'update',
            'assess_risk',
            'manage_engagements',
            'assign_team',
            'submit',
            'review',
            'approve',
            'activate',
            'complete',
            'create_revision',
            'archive',
            'restore',
            'export',
        ],
        'aem' => ['view', 'create', 'update', 'assign', 'manage_workpapers', 'review', 'close'],
        'afr' => ['view', 'create', 'update', 'submit', 'review', 'approve'],
        'cms' => ['view', 'update', 'submit_evidence', 'validate', 'approve_extension', 'close'],
        'arms' => ['view', 'manage'],
        'ais' => ['view', 'export'],
        'documents' => ['view', 'upload', 'update', 'download', 'delete', 'restore'],
        'notifications' => ['view', 'manage'],
        'activity_logs' => ['view'],
        'audit_logs' => ['view', 'export'],
        'system_configuration' => ['view', 'manage'],
        'administrative_reports' => ['view', 'export'],
        'profile' => ['view', 'update', 'change_password'],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach ($this->permissionCatalogue as $module => $actions) {
            foreach ($actions as $action) {
                $code = "{$module}.{$action}";
                $permission = Permission::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => str("{$action} {$module}")->replace('_', ' ')->headline()->toString(),
                        'module' => $module,
                        'action' => $action,
                        'description' => "Allows the {$action} action in the {$module} module.",
                    ],
                );

                $permissionIds[$code] = $permission->id;
            }
        }

        $roles = [
            'platform_admin' => [
                'name' => 'Platform Administrator',
                'description' => 'Full access to AGIS configuration, registries, audit modules, and reports.',
                'is_system' => true,
                'permissions' => array_keys($permissionIds),
            ],
            'agis_admin' => [
                'name' => 'AGIS Administrator',
                'description' => 'Administration of registries, master lists, access, configuration, and platform monitoring.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'offices.view', 'offices.create', 'offices.update', 'offices.delete',
                    'offices.restore',
                    'audit_areas.view', 'audit_areas.create', 'audit_areas.update', 'audit_areas.delete',
                    'audit_areas.restore',
                    'audit_focus.view', 'audit_focus.create', 'audit_focus.update', 'audit_focus.delete',
                    'audit_focus.restore',
                    'users.view', 'users.create', 'users.update', 'users.deactivate',
                    'users.restore',
                    'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.restore',
                    'permissions.view', 'permissions.update',
                    'master_lists.view', 'master_lists.manage',
                    'iap.view', 'aem.view', 'afr.view', 'cms.view', 'arms.view', 'ais.view',
                    'documents.view', 'documents.upload', 'documents.update',
                    'documents.download', 'documents.delete', 'documents.restore',
                    'notifications.view', 'notifications.manage',
                    'activity_logs.view', 'audit_logs.view',
                    'system_configuration.view', 'system_configuration.manage',
                    'administrative_reports.view', 'administrative_reports.export',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'cias_management' => [
                'name' => 'CIAS Management',
                'description' => 'CIAS leadership oversight, review, approval, resource management, and reporting.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view', 'offices.view', 'audit_areas.view', 'audit_focus.view',
                    'users.view', 'master_lists.view',
                    'iap.view', 'iap.create', 'iap.update', 'iap.assess_risk',
                    'iap.manage_universe',
                    'iap.manage_engagements', 'iap.assign_team', 'iap.submit',
                    'iap.review', 'iap.approve', 'iap.activate', 'iap.complete',
                    'iap.create_revision', 'iap.archive', 'iap.restore', 'iap.export',
                    'aem.view', 'aem.create', 'aem.update', 'aem.assign', 'aem.review', 'aem.close',
                    'afr.view', 'afr.create', 'afr.update', 'afr.review', 'afr.approve',
                    'cms.view', 'cms.update', 'cms.validate', 'cms.approve_extension', 'cms.close',
                    'arms.view', 'arms.manage', 'ais.view', 'ais.export',
                    'documents.view', 'documents.upload', 'documents.update',
                    'documents.download', 'documents.delete', 'documents.restore',
                    'notifications.view', 'notifications.manage',
                    'activity_logs.view', 'audit_logs.view',
                    'administrative_reports.view', 'administrative_reports.export',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'agis_user' => [
                'name' => 'AGIS User',
                'description' => 'Operational access to assigned plans, engagements, findings, and evidence.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'offices.view', 'audit_areas.view', 'audit_focus.view', 'master_lists.view',
                    'iap.view', 'iap.update', 'iap.assess_risk',
                    'iap.manage_engagements', 'iap.assign_team',
                    'aem.view', 'aem.update', 'aem.manage_workpapers',
                    'afr.view', 'afr.create', 'afr.update', 'afr.submit',
                    'cms.view', 'cms.update', 'cms.submit_evidence',
                    'arms.view', 'ais.view',
                    'documents.view', 'documents.upload', 'documents.download',
                    'notifications.view',
                    'administrative_reports.view',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'auditee_representative' => [
                'name' => 'Auditee Representative',
                'description' => 'Office representative access to assigned engagements, findings, requests, and evidence.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'cms.view', 'cms.update', 'cms.submit_evidence',
                    'documents.view', 'documents.upload', 'documents.download',
                    'notifications.view',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'read_only' => [
                'name' => 'Read Only User',
                'description' => 'Inquiry-only access to authorized dashboards, registries, audit records, and reports.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view', 'offices.view', 'audit_areas.view', 'audit_focus.view',
                    'master_lists.view', 'iap.view', 'aem.view', 'afr.view', 'cms.view',
                    'arms.view', 'ais.view', 'documents.view', 'notifications.view',
                    'administrative_reports.view',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
        ];

        foreach ($roles as $code => $attributes) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'description' => $attributes['description'],
                    'is_system' => $attributes['is_system'],
                    'is_active' => true,
                ],
            );

            $role->permissions()->sync(
                collect($attributes['permissions'])
                    ->map(fn (string $permission) => $permissionIds[$permission])
                    ->all(),
            );
        }

        Permission::query()
            ->whereIn('code', ['iap.delete'])
            ->delete();

        $legacyRoles = [
            'system_admin' => 'platform_admin',
            'audit_manager' => 'cias_management',
            'internal_auditor' => 'agis_user',
        ];

        foreach ($legacyRoles as $legacyCode => $replacementCode) {
            $legacyRole = Role::query()->where('code', $legacyCode)->first();
            $replacementRole = Role::query()->where('code', $replacementCode)->firstOrFail();

            if (! $legacyRole) {
                continue;
            }

            $legacyRole->users()->update(['role_id' => $replacementRole->id]);
            $legacyRole->delete();
        }
    }
}
