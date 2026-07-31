<?php

namespace App\Services;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Contracts\Aems\IapEngagementGateway;
use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\User;
use App\Support\ActivityRecorder;

/** Describes the active module providers and shared Core capabilities used by AEMS. */
class AemsIntegrationStatusService
{
    public function __construct(
        private readonly IapEngagementGateway $iap,
        private readonly CmsRecommendationGateway $cms,
        private readonly ResourcePlanningGateway $resources,
        private readonly RuntimeConfiguration $runtime,
        private readonly NotificationService $notifications,
        private readonly DocumentAccessService $documents,
        private readonly WorkflowEngine $workflows,
    ) {}

    /** @return array<string, mixed> */
    public function status(User $user): array
    {
        // Referencing the resolved services is deliberate: container failures
        // surface immediately instead of reporting a false healthy capability.
        $coreProviders = [
            'documentVersioning' => $this->documents::class,
            'workflowEngine' => $this->workflows::class,
            'notifications' => $this->notifications::class,
            'activityAndAudit' => ActivityRecorder::class,
            'runtimeConfiguration' => $this->runtime::class,
        ];

        $iap = $this->iap->status();
        $cms = $this->cms->status();
        if (! $user->hasGlobalEngagementAccess()) {
            unset(
                $iap['eligibleEngagements'],
                $cms['transferredRecommendations'],
                $cms['operationalCases'],
            );
        }

        return [
            'core' => [
                'module' => 'CORE',
                'available' => true,
                'mode' => 'SHARED_SERVICES',
                'providers' => $coreProviders,
                'capabilities' => [
                    'users_and_offices',
                    'roles_permissions_scopes',
                    'audit_areas_and_focuses',
                    'master_lists',
                    'document_versioning',
                    'workflow_engine',
                    'notifications',
                    'activity_logs',
                    'audit_trails',
                    'runtime_configuration',
                    'numbering',
                ],
                'workflowMode' => 'AEMS_DOMAIN_GUARDED_WITH_CORE_INFRASTRUCTURE',
                'timezone' => $this->runtime->timezone(),
                'paginationSize' => $this->runtime->paginationSize(),
                'documentUploadMaxKilobytes' => $this->runtime->documentUploadMaxKilobytes(),
            ],
            'iap' => $iap,
            'cms' => $cms,
            'armis' => $this->resources->status(),
        ];
    }
}
