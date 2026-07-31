<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\IapEngagementGateway;
use App\Models\IapPlanEngagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Reads approved Annual Plan engagements without allowing AEMS to edit IAP content. */
class DatabaseIapEngagementGateway implements IapEngagementGateway
{
    public function eligibleForImport(): Collection
    {
        return $this->eligibleQuery()
            ->with($this->relations())
            ->orderBy('planned_start_date')
            ->orderBy('engagement_code')
            ->get();
    }

    public function lockForImport(int $sourceId): IapPlanEngagement
    {
        return IapPlanEngagement::query()
            ->whereKey($sourceId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail()
            ->load($this->relations());
    }

    public function markImported(IapPlanEngagement $source, int $engagementId): void
    {
        $source->forceFill(['aem_engagement_id' => $engagementId])->save();
    }

    public function relink(int $sourceId, int $engagementId): void
    {
        IapPlanEngagement::withTrashed()
            ->whereKey($sourceId)
            ->update(['aem_engagement_id' => $engagementId]);
    }

    public function status(): array
    {
        return [
            'module' => 'IAP',
            'provider' => self::class,
            'mode' => 'APPROVED_PLAN_IMPORT',
            'available' => true,
            'eligibleEngagements' => $this->eligibleQuery()->count(),
            'ownership' => 'READ_APPROVED_SNAPSHOT',
        ];
    }

    private function eligibleQuery(): Builder
    {
        return IapPlanEngagement::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('schedule_status')
                ->orWhere('schedule_status', '<>', 'CANCELLED'))
            ->whereHas('plan', fn ($plan) => $plan
                ->whereIn('status', ['APPROVED', 'ACTIVE'])
                ->where('is_active', true))
            ->whereDoesntHave(
                'aemEngagement',
                fn ($engagement) => $engagement->withTrashed(),
            );
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'plan.prioritizationRun.riskPeriod',
            'engagementType',
            'auditApproach',
            'priority',
            'riskLevel',
            'prioritizationItem.run.riskPeriod',
            'prioritizationItem.riskAssessment.scores.criterion',
            'prioritizationItem.riskAssessment.inherentRiskLevel',
            'prioritizationItem.riskAssessment.residualRiskLevel',
            'auditUniverseItem.responsibleOffice',
            'auditUniverseItem.primaryAuditArea',
            'universeRiskAssessment.scores.criterion',
            'universeRiskAssessment.period',
            'universeRiskAssessment.inherentRiskLevel',
            'universeRiskAssessment.residualRiskLevel',
            'offices',
            'auditAreas',
            'auditFocuses',
        ];
    }
}
