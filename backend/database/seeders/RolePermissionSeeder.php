<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds standard AGIS roles, granular permissions, and default access scopes.
 */
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
        'users' => [
            'view',
            'create',
            'update',
            'activate',
            'deactivate',
            'archive',
            'restore',
            'lock',
            'unlock',
            'reset_password',
        ],
        'roles' => ['view', 'create', 'clone', 'update', 'delete', 'restore'],
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
        'aems.engagement' => [
            'view', 'create', 'update', 'transition', 'authorize', 'suspend', 'cancel',
            'archive', 'restore', 'close', 'export', 'reopen_request', 'reopen_approve',
        ],
        'aems.team' => ['view', 'assign', 'reassign'],
        'aems.aeo' => ['view', 'prepare', 'review', 'approve', 'issue', 'revise'],
        'aems.aep' => ['view', 'create', 'review', 'approve', 'revise'],
        'aems.program' => ['view', 'manage', 'review', 'approve'],
        'aems.working-paper' => ['view', 'create', 'review', 'approve'],
        'aems.evidence' => ['view', 'upload', 'verify', 'void'],
        'aems.issue' => ['view', 'create', 'validate', 'dismiss', 'convert'],
        'aems.finding' => ['view', 'create', 'review', 'validate', 'communicate', 'finalize'],
        'aems.management-response' => ['view', 'submit', 'request_clarification'],
        'aems.rejoinder' => ['view', 'create', 'finalize'],
        'aems.conference' => ['view', 'manage', 'acknowledge'],
        'aems.entry-conference' => ['view', 'manage', 'acknowledge', 'waive'],
        'aems.report' => ['view', 'create', 'review', 'approve', 'issue', 'view_issued'],
        'aems.completion-assessment' => ['view', 'create', 'update', 'submit', 'review', 'approve'],
        'aems.closure' => ['view', 'create', 'update', 'submit', 'review', 'approve', 'close'],
        'aems.document-index' => ['view', 'manage', 'finalize'],
        'aems.retention' => ['view', 'manage', 'approve'],
        'afr' => ['view', 'create', 'update', 'submit', 'review', 'approve'],
        'cms' => [
            'view', 'update', 'submit_evidence', 'validate', 'approve_extension', 'close',
            'dashboard.view', 'recommendation.view', 'recommendation.assign',
            'recommendation.monitor', 'administration.monitor',
        ],
        'arms' => ['view', 'manage'],
        'ais' => ['view', 'export'],
        'documents' => [
            'view', 'view_confidential', 'view_restricted',
            'upload', 'update', 'download', 'delete', 'restore',
        ],
        'notifications' => ['view', 'manage'],
        'workflows' => [
            'view',
            'create',
            'update',
            'publish',
            'archive',
            'restore',
            'start',
            'act',
            'monitor',
        ],
        'activity_logs' => ['view', 'export'],
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
                'description' => 'Full platform configuration, registry, monitoring, and issued-report access without audit approval authority.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => collect(array_keys($permissionIds))
                    ->reject(fn (string $code): bool => str_starts_with($code, 'aems.')
                        || str_starts_with($code, 'aem.')
                        || in_array($code, [
                            'cms.dashboard.view',
                            'cms.recommendation.view',
                            'cms.recommendation.assign',
                            'cms.recommendation.monitor',
                            'cms.administration.monitor',
                        ], true))
                    ->merge([
                        'aem.view',
                        'cms.administration.monitor',
                        'aems.engagement.view',
                        'aems.team.view',
                        'aems.entry-conference.view',
                        'aems.report.view_issued',
                        'aems.completion-assessment.view',
                        'aems.closure.view',
                        'aems.document-index.view',
                        'aems.retention.view',
                    ])
                    ->all(),
            ],
            'agis_admin' => [
                'name' => 'AGIS Administrator',
                'description' => 'Administration of registries, master lists, access, configuration, and platform monitoring.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => [
                    'dashboard.view',
                    'offices.view', 'offices.create', 'offices.update', 'offices.delete',
                    'offices.restore',
                    'audit_areas.view', 'audit_areas.create', 'audit_areas.update', 'audit_areas.delete',
                    'audit_areas.restore',
                    'audit_focus.view', 'audit_focus.create', 'audit_focus.update', 'audit_focus.delete',
                    'audit_focus.restore',
                    'users.view', 'users.create', 'users.update', 'users.deactivate',
                    'users.activate', 'users.archive', 'users.restore', 'users.lock', 'users.unlock',
                    'roles.view', 'roles.create', 'roles.clone', 'roles.update', 'roles.delete', 'roles.restore',
                    'permissions.view', 'permissions.update',
                    'master_lists.view', 'master_lists.manage',
                    'iap.view', 'aem.view', 'afr.view', 'cms.view', 'arms.view', 'ais.view',
                    'cms.administration.monitor',
                    'aems.engagement.view', 'aems.team.view', 'aems.report.view_issued',
                    'aems.completion-assessment.view', 'aems.closure.view',
                    'aems.document-index.view', 'aems.retention.view',
                    'documents.view', 'documents.upload', 'documents.update',
                    'documents.view_confidential', 'documents.view_restricted',
                    'documents.download', 'documents.delete', 'documents.restore',
                    'notifications.view', 'notifications.manage',
                    'workflows.view', 'workflows.create', 'workflows.update',
                    'workflows.publish', 'workflows.archive', 'workflows.restore',
                    'workflows.start', 'workflows.act', 'workflows.monitor',
                    'activity_logs.view', 'activity_logs.export', 'audit_logs.view', 'audit_logs.export',
                    'system_configuration.view', 'system_configuration.manage',
                    'administrative_reports.view', 'administrative_reports.export',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'cias_management' => [
                'name' => 'CIAS Management',
                'description' => 'CIAS leadership oversight, review, approval, resource management, and reporting.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => [
                    'dashboard.view', 'offices.view', 'audit_areas.view', 'audit_focus.view',
                    'users.view', 'master_lists.view',
                    'iap.view', 'iap.create', 'iap.update', 'iap.assess_risk',
                    'iap.manage_universe',
                    'iap.manage_engagements', 'iap.assign_team', 'iap.submit',
                    'iap.review', 'iap.approve', 'iap.activate', 'iap.complete',
                    'iap.create_revision', 'iap.archive', 'iap.restore', 'iap.export',
                    'aem.view', 'aem.create', 'aem.update', 'aem.assign', 'aem.review', 'aem.close',
                    ...collect(array_keys($permissionIds))
                        ->filter(fn (string $code): bool => str_starts_with($code, 'aems.'))
                        ->all(),
                    'afr.view', 'afr.create', 'afr.update', 'afr.review', 'afr.approve',
                    'cms.view', 'cms.update', 'cms.validate', 'cms.approve_extension', 'cms.close',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'cms.recommendation.assign', 'cms.recommendation.monitor',
                    'arms.view', 'arms.manage', 'ais.view', 'ais.export',
                    'documents.view', 'documents.upload', 'documents.update',
                    'documents.view_confidential', 'documents.view_restricted',
                    'documents.download', 'documents.delete', 'documents.restore',
                    'notifications.view', 'notifications.manage',
                    'workflows.view', 'workflows.start', 'workflows.act', 'workflows.monitor',
                    'activity_logs.view', 'activity_logs.export', 'audit_logs.view', 'audit_logs.export',
                    'administrative_reports.view', 'administrative_reports.export',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'agis_user' => [
                'name' => 'AGIS User',
                'description' => 'Operational access to assigned plans, engagements, findings, and evidence.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ASSIGNED',
                'permissions' => [
                    'dashboard.view',
                    'offices.view', 'audit_areas.view', 'audit_focus.view', 'master_lists.view',
                    'iap.view', 'iap.update', 'iap.assess_risk',
                    'iap.manage_engagements', 'iap.assign_team',
                    'aem.view', 'aem.update', 'aem.manage_workpapers',
                    'aems.engagement.view', 'aems.engagement.update',
                    'aems.engagement.transition',
                    'aems.team.view',
                    'aems.aeo.view', 'aems.aeo.prepare', 'aems.aeo.review',
                    'aems.aep.view', 'aems.aep.create', 'aems.aep.review',
                    'aems.program.view', 'aems.program.manage', 'aems.program.review',
                    'aems.working-paper.view', 'aems.working-paper.create',
                    'aems.working-paper.review', 'aems.working-paper.approve',
                    'aems.evidence.view', 'aems.evidence.upload', 'aems.evidence.verify',
                    'aems.issue.view', 'aems.issue.create', 'aems.issue.validate',
                    'aems.finding.view', 'aems.finding.create', 'aems.finding.review',
                    'aems.finding.validate',
                    'aems.management-response.view',
                    'aems.management-response.request_clarification',
                    'aems.rejoinder.view', 'aems.rejoinder.create', 'aems.rejoinder.finalize',
                    'aems.conference.view', 'aems.conference.manage',
                    'aems.entry-conference.view', 'aems.entry-conference.manage',
                    'aems.entry-conference.acknowledge',
                    'aems.report.view', 'aems.report.create', 'aems.report.review',
                    'aems.report.view_issued',
                    'aems.completion-assessment.view', 'aems.completion-assessment.create',
                    'aems.completion-assessment.update', 'aems.completion-assessment.submit',
                    'aems.completion-assessment.review',
                    'aems.closure.view', 'aems.closure.create', 'aems.closure.update',
                    'aems.closure.submit', 'aems.closure.review',
                    'aems.document-index.view', 'aems.document-index.manage',
                    'aems.retention.view', 'aems.retention.manage',
                    'aems.engagement.reopen_request',
                    'afr.view', 'afr.create', 'afr.update', 'afr.submit',
                    'cms.view', 'cms.update', 'cms.submit_evidence',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'cms.recommendation.monitor',
                    'arms.view', 'ais.view',
                    'documents.view', 'documents.upload', 'documents.download',
                    'documents.view_confidential',
                    'notifications.view',
                    'workflows.start', 'workflows.act',
                    'administrative_reports.view',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'auditee_representative' => [
                'name' => 'Auditee Representative',
                'description' => 'Office representative access to assigned engagements, findings, requests, and evidence.',
                'is_system' => true,
                'office_access_scope' => 'OWN_OFFICE',
                'engagement_access_scope' => 'ASSIGNED',
                'permissions' => [
                    'dashboard.view',
                    'aems.finding.view',
                    'aems.management-response.view', 'aems.management-response.submit',
                    'aems.rejoinder.view',
                    'aems.conference.view', 'aems.conference.acknowledge',
                    'aems.entry-conference.view', 'aems.entry-conference.acknowledge',
                    'aems.report.view_issued',
                    'cms.view', 'cms.update', 'cms.submit_evidence',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'documents.view', 'documents.upload', 'documents.download',
                    'notifications.view',
                    'workflows.act',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'read_only' => [
                'name' => 'Read Only User',
                'description' => 'Inquiry-only access to authorized dashboards, registries, audit records, and reports.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => [
                    'dashboard.view', 'offices.view', 'audit_areas.view', 'audit_focus.view',
                    'master_lists.view', 'iap.view', 'aem.view', 'afr.view', 'cms.view',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'aems.report.view_issued',
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
                    'office_access_scope' => $attributes['office_access_scope'],
                    'engagement_access_scope' => $attributes['engagement_access_scope'],
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

            $legacyRole->assignedUsers()->withTrashed()->get()->each(
                function (User $user) use ($legacyRole, $replacementRole): void {
                    $roleIds = $user->roles()
                        ->where('roles.id', '<>', $legacyRole->id)
                        ->pluck('roles.id')
                        ->push($replacementRole->id)
                        ->unique()
                        ->values()
                        ->all();
                    $primaryRoleId = (int) $user->role_id === (int) $legacyRole->id
                        ? $replacementRole->id
                        : $user->role_id;
                    $user->syncRoleAssignments($roleIds, $primaryRoleId);
                },
            );
            $legacyRole->users()->update(['role_id' => $replacementRole->id]);
            $legacyRole->delete();
        }
    }
}
