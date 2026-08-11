<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\ArmisProviderAuthorityDecision;
use App\Models\ArmisProviderReconciliationRun;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Services\RuntimeConfiguration;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the AEMS provider boundary explicit while ARMIS is introduced.
 *
 * ARMIS-6A supports IAP fallback and ARMIS shadow modes only. In both modes
 * AEMS continues reading IAP, while the ARMIS adapter is available for the
 * later reconciliation phase. Unsupported or future authority values fail
 * closed to the IAP fallback.
 */
class ConfigurableResourcePlanningGateway implements ResourcePlanningGateway
{
    public const IAP_INTERIM_FALLBACK = 'IAP_INTERIM_FALLBACK';

    public const ARMIS_SHADOW = 'ARMIS_SHADOW';

    public const ARMIS_AUTHORITATIVE = 'ARMIS_AUTHORITATIVE';

    /** @var list<string> */
    public const SUPPORTED_MODES = [
        self::IAP_INTERIM_FALLBACK,
        self::ARMIS_SHADOW,
        self::ARMIS_AUTHORITATIVE,
    ];

    public function __construct(
        private readonly RuntimeConfiguration $runtime,
        private readonly InterimIapResourcePlanningGateway $interim,
        private readonly ArmisResourcePlanningGateway $armis,
    ) {}

    public function capacityFor(int $year, int $userId): float
    {
        return $this->active()->capacityFor($year, $userId);
    }

    public function engagementActualPersonDays(AuditEngagement $engagement): float
    {
        return $this->active()->engagementActualPersonDays($engagement);
    }

    public function assignmentActualPersonDays(EngagementTeam $assignment): float
    {
        return $this->active()->assignmentActualPersonDays($assignment);
    }

    public function skills(array $userIds, array $specializationIds = []): array
    {
        return $this->active()->skills($userIds, $specializationIds);
    }

    public function requirements(AuditEngagement $engagement): array
    {
        return $this->active()->requirements($engagement);
    }

    public function unavailability(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        return $this->active()->unavailability($userId, $start, $end);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $mode = $this->mode();
        $active = $this->active()->status();
        $armis = $this->armis->status();
        $mode = $this->mode();
        $authorityEligible = $this->authorityEligible();

        return [
            ...$active,
            'provider' => $active['provider'],
            'mode' => $mode,
            'configuredMode' => $this->runtime->armisProviderMode(),
            'activeProvider' => $active['provider'],
            'fallbackProvider' => $this->interim->status()['provider'],
            'shadowProvider' => $armis['provider'],
            'shadowAvailable' => (bool) ($armis['available'] ?? false),
            'authoritative' => $mode === self::ARMIS_AUTHORITATIVE,
            'authoritySwitchAllowed' => $mode !== self::ARMIS_AUTHORITATIVE && $authorityEligible,
            'authoritySwitchReason' => $mode === self::ARMIS_AUTHORITATIVE
                ? 'ARMIS is authoritative. Use the explicit rollback decision to return to IAP.'
                : ($authorityEligible
                    ? 'An independently accepted ARMIS shadow reconciliation is available for authority approval.'
                    : 'An independently accepted ARMIS shadow reconciliation is required before authority approval.'),
            'supportedModes' => self::SUPPORTED_MODES,
            'armisAdapter' => $armis,
            'authorityEligible' => $authorityEligible,
            'actualPersonDaysOwner' => $mode === self::ARMIS_AUTHORITATIVE
                ? 'ARMIS'
                : 'AEMS_UNTIL_ARMIS_AUTHORITY_GATE',
            'futureAuthoritativeProvider' => $mode === self::ARMIS_AUTHORITATIVE ? null : 'ARMIS',
        ];
    }

    public function mode(): string
    {
        $configured = strtoupper(trim($this->runtime->string('armis_provider_mode')));

        if (! in_array($configured, self::SUPPORTED_MODES, true)) {
            return self::IAP_INTERIM_FALLBACK;
        }
        if ($configured === self::ARMIS_AUTHORITATIVE) {
            if (! Schema::hasTable('armis_provider_authority_decisions')) {
                return self::IAP_INTERIM_FALLBACK;
            }
            $latest = ArmisProviderAuthorityDecision::query()->latest('decided_at')->first();
            if ($latest?->to_mode !== self::ARMIS_AUTHORITATIVE) {
                return self::IAP_INTERIM_FALLBACK;
            }
        }

        return $configured;
    }

    private function active(): ResourcePlanningGateway
    {
        return $this->mode() === self::ARMIS_AUTHORITATIVE
            ? $this->armis
            : $this->interim;
    }

    private function authorityEligible(): bool
    {
        if (! Schema::hasTable('armis_provider_reconciliation_runs')) {
            return false;
        }

        return ArmisProviderReconciliationRun::query()
            ->where('status', 'GENERATED')
            ->where('provider_mode', self::ARMIS_SHADOW)
            ->whereHas('reviews', fn ($query) => $query->where('decision', 'ACCEPTED'))
            ->exists();
    }
}
