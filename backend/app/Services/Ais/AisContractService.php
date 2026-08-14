<?php

namespace App\Services\Ais;

use App\Models\User;

/**
 * AIS governance and conformance contract.
 *
 * This service deliberately exposes metadata only. AIS does not own or mutate
 * operational records. AIS-1 adds read-only aggregation snapshots, AIS-2 adds
 * analytical presentation, AIS-3 adds immutable reports and protected exports,
 * and AIS-4 hardens the surface for authenticated deployment.
 */
class AisContractService
{
    public const VERSION = 'AIS-4.0';

    /** @return array<string, mixed> */
    public function contract(User $user): array
    {
        abort_unless($user->is_active && ! $user->trashed() && $user->hasPermission('ais.view'), 403, 'You do not have permission to access the AIS governance contract.');

        return [
            'contractVersion' => self::VERSION,
            'status' => 'READ_ONLY_HARDENED',
            'enabled' => false,
            'readOnlyDashboardEnabled' => true,
            'readOnlyReportsEnabled' => true,
            'protectedExportsEnabled' => $user->hasPermission('ais.export'),
            'displayName' => 'Audit Intelligence System',
            'purpose' => 'Read-only intelligence and analytical presentation of approved records from AGIS operational modules.',
            'scope' => [
                'office' => $user->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE',
                'engagement' => $user->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
                'confidentiality' => [
                    'confidential' => $user->hasPermission('documents.view_confidential') || $user->hasPermission('documents.view_restricted'),
                    'restricted' => $user->hasPermission('documents.view_restricted'),
                ],
            ],
            'sourceModules' => [
                [
                    'module' => 'CORE',
                    'authority' => 'Core',
                    'consumedRecords' => ['users', 'offices', 'audit areas', 'audit focuses', 'document classifications'],
                    'mode' => 'READ_ONLY',
                ],
                [
                    'module' => 'IAP',
                    'authority' => 'IAP',
                    'consumedRecords' => ['approved strategic plans', 'approved annual plans', 'risk and prioritization results'],
                    'mode' => 'READ_ONLY',
                ],
                [
                    'module' => 'AEMS',
                    'authority' => 'AEMS',
                    'consumedRecords' => ['engagement progress', 'fieldwork', 'evidence', 'findings', 'reports', 'closure status'],
                    'mode' => 'READ_ONLY',
                ],
                [
                    'module' => 'CMS',
                    'authority' => 'CMS',
                    'consumedRecords' => ['finalized recommendations', 'action plans', 'monitoring and validation outcomes'],
                    'mode' => 'READ_ONLY',
                ],
                [
                    'module' => 'ARMIS',
                    'authority' => 'ARMIS',
                    'consumedRecords' => ['resource availability', 'competencies', 'workload', 'person-day reconciliation'],
                    'mode' => 'READ_ONLY',
                ],
            ],
            'professionalControls' => [
                'noOperationalWrites' => true,
                'noProfessionalDecisions' => true,
                'noAutomaticFindingValidation' => true,
                'noAutomaticRecommendationClosure' => true,
                'noAutomaticProviderAuthorityChange' => true,
                'sourceScopeRechecked' => true,
                'confidentialityRechecked' => true,
                'humanReviewRequiredForPublishedInsight' => true,
            ],
            'hardening' => $this->hardening($user),
            'plannedCapabilities' => [
                ['code' => 'AIS-1', 'label' => 'Read-only data foundation and aggregation', 'status' => 'IMPLEMENTED'],
                ['code' => 'AIS-2', 'label' => 'Intelligence dashboard and analytical views', 'status' => 'IMPLEMENTED'],
                ['code' => 'AIS-3', 'label' => 'Reports, trends, alerts, and protected exports', 'status' => 'IMPLEMENTED'],
                ['code' => 'AIS-4', 'label' => 'Security, performance, audit, and deployment hardening', 'status' => 'IMPLEMENTED'],
            ],
            'permissions' => [
                'ais.view' => $user->hasPermission('ais.view'),
                'ais.export' => $user->hasPermission('ais.export'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function hardening(User $user): array
    {
        abort_unless($user->is_active && ! $user->trashed() && $user->hasPermission('ais.view'), 403, 'You do not have permission to access the AIS hardening contract.');

        return [
            'version' => (string) config('ais.hardening_version', self::VERSION),
            'status' => 'ENFORCED',
            'checks' => [
                'authRequired' => true,
                'aisViewPermissionRequired' => true,
                'aisExportPermissionRequired' => true,
                'sourceScopeRechecked' => true,
                'confidentialityRechecked' => true,
                'immutableSnapshots' => true,
                'immutableReportRuns' => true,
                'immutableExports' => true,
                'privateStorage' => true,
                'protectedDownloads' => true,
                'checksumHeaders' => true,
                'csvFormulaMitigation' => true,
                'noPublicUrls' => true,
                'diagnosticsRedacted' => true,
                'privateResponseCaching' => true,
                'namedRateLimits' => true,
                'noOperationalWrites' => true,
                'humanReviewRequired' => true,
            ],
            'rateLimits' => [
                'readPerMinute' => (int) config('ais.read_rate_limit', 120),
                'generatePerMinute' => (int) config('ais.generate_rate_limit', 12),
                'exportPerMinute' => (int) config('ais.export_rate_limit', 20),
            ],
            'cache' => [
                'readSeconds' => (int) config('ais.read_cache_seconds', 30),
                'visibility' => 'PRIVATE',
                'revalidation' => 'MUST_REVALIDATE',
            ],
        ];
    }
}
