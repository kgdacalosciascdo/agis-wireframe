<?php

namespace App\Services;

use App\Contracts\Aems\IapEngagementGateway;
use App\Models\AuditEngagement;
use App\Models\AuditFocus;
use App\Models\IapPlanEngagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the AEMS engagement aggregate from an immutable IAP snapshot or from
 * separately authorized special-engagement information.
 */
class AemsEngagementRegistryService
{
    public function __construct(
        private readonly AemsSupport $support,
        private readonly IapEngagementGateway $iap,
        private readonly AemsAccessService $access,
    ) {}

    /** @return Collection<int, IapPlanEngagement> */
    public function eligibleIapEngagements(): Collection
    {
        return $this->iap->eligibleForImport();
    }

    public function import(
        Request $request,
        int $sourceId,
        ?string $requestedCode = null,
    ): AuditEngagement {
        return DB::transaction(function () use ($request, $sourceId, $requestedCode): AuditEngagement {
            $source = $this->iap->lockForImport($sourceId);

            $this->assertImportable($source);
            if (AuditEngagement::withTrashed()
                ->where('iap_plan_engagement_id', $source->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'iapPlanEngagementId' => [
                        'This IAP engagement was already imported. Restore its archived AEMS engagement instead of creating a duplicate.',
                    ],
                ]);
            }

            $officeIds = $source->offices->pluck('id')->map(fn ($id): int => (int) $id)->values();
            $this->assertSingleEngagementOffice($officeIds->all(), 'IAP engagement');
            $projection = AuditEngagement::lifecycleProjectionForStatus('DRAFT');
            $snapshot = $this->sourceSnapshot($source, $request->user());
            $engagement = AuditEngagement::query()->create([
                'engagement_code' => $requestedCode ?: $this->nextCode(
                    (int) $source->plan->fiscal_year,
                ),
                'title' => $source->title,
                'source_type' => 'PLANNED',
                'iap_plan_engagement_id' => $source->id,
                'iap_plan_id' => $source->plan_id,
                'iap_prioritization_item_id' => $source->prioritization_item_id,
                'iap_risk_assessment_id' => $source->universe_risk_assessment_id,
                'iap_audit_universe_item_id' => $source->audit_universe_item_id,
                'source_snapshot' => $snapshot,
                'engagement_office_id' => $officeIds->first(),
                'audit_type_id' => $source->engagement_type_id,
                'engagement_approach_id' => $source->audit_approach_id,
                'background' => $source->background,
                'objectives' => $source->objectives,
                'scope' => $source->scope,
                'exclusions' => $source->exclusions,
                'planned_start_date' => $source->planned_start_date,
                'planned_end_date' => $source->planned_end_date,
                'expected_report_date' => $source->expected_report_date,
                'planned_person_days' => $source->estimated_person_days,
                'status' => 'DRAFT',
                ...$projection,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);

            $engagement->offices()->attach($officeIds->first(), ['is_primary' => true]);
            $engagement->auditAreas()->sync($source->auditAreas->pluck('id')->all());
            $engagement->auditFocuses()->sync($source->auditFocuses->pluck('id')->all());
            $this->iap->markImported($source, $engagement->id);

            $newValues = $this->auditSnapshot($engagement);
            $this->support->event(
                $request,
                $engagement,
                'IMPORT_FROM_IAP',
                null,
                'DRAFT',
                null,
                $newValues,
                "Imported approved IAP item {$source->engagement_code}.",
            );
            $this->support->audit(
                $request,
                'aems.engagement.imported',
                $engagement,
                null,
                $newValues,
                ['iapPlanEngagementId' => $source->id],
            );

            return $engagement;
        });
    }

    /** @param array<string, mixed> $validated */
    public function createSpecial(Request $request, array $validated): AuditEngagement
    {
        return DB::transaction(function () use ($request, $validated): AuditEngagement {
            if ((int) $validated['specialAuthorityApprovedBy'] === (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'specialAuthorityApprovedBy' => [
                        'The registry creator cannot also be the recorded special-engagement approving authority.',
                    ],
                ]);
            }
            $this->validateCoverage($validated);
            $projection = AuditEngagement::lifecycleProjectionForStatus('AUTHORIZED');
            $year = (int) substr((string) $validated['specialAuthorityDate'], 0, 4);
            $engagement = AuditEngagement::query()->create([
                ...$this->mutableAttributes($validated),
                'engagement_code' => $validated['engagementCode'] ?: $this->nextCode($year),
                'source_type' => 'SPECIAL',
                'special_authority_reference' => $validated['specialAuthorityReference'],
                'special_authority_type_code' => $validated['specialAuthorityTypeCode'] ?? null,
                'special_authority_date' => $validated['specialAuthorityDate'],
                'special_authority_approved_by' => $validated['specialAuthorityApprovedBy'],
                'source_snapshot' => [
                    'schemaVersion' => 1,
                    'capturedAt' => now()->toISOString(),
                    'capturedBy' => $request->user()->id,
                    'sourceType' => 'SPECIAL',
                    'authority' => [
                        'reference' => $validated['specialAuthorityReference'],
                        'typeCode' => $validated['specialAuthorityTypeCode'] ?? null,
                        'date' => $validated['specialAuthorityDate'],
                        'approvedBy' => $validated['specialAuthorityApprovedBy'],
                    ],
                ],
                'status' => 'AUTHORIZED',
                ...$projection,
                'engagement_office_id' => (int) $validated['officeIds'][0],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $this->syncCoverage($engagement, $validated);

            $newValues = $this->auditSnapshot($engagement);
            $this->support->event(
                $request,
                $engagement,
                'CREATE_SPECIAL',
                null,
                'AUTHORIZED',
                null,
                $newValues,
                'Created from separately approved special or unplanned authority.',
            );
            $this->support->audit(
                $request,
                'aems.engagement.special_created',
                $engagement,
                null,
                $newValues,
            );

            return $engagement;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        array $validated,
    ): AuditEngagement {
        return DB::transaction(function () use ($request, $engagement, $validated): AuditEngagement {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            if ((int) $validated['lockVersion'] !== (int) $locked->lock_version) {
                throw ValidationException::withMessages([
                    'lockVersion' => [
                        'This engagement was changed by another user. Refresh before saving.',
                    ],
                ]);
            }
            if (! in_array($locked->status, ['DRAFT', 'AUTHORIZATION_PREPARATION', 'AUTHORIZED'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Engagement registry details are frozen after fieldwork begins.'],
                ]);
            }
            $this->access->authorizeEngagementAction(
                $request->user(),
                $locked,
                'aems.foundation.manage_scope',
            );
            $this->validateCoverage($validated);
            $oldValues = $this->auditSnapshot($locked);
            $attributes = $this->mutableAttributes($validated);
            if ($locked->source_type === 'SPECIAL') {
                $attributes = [
                    ...$attributes,
                    'special_authority_reference' => $validated['specialAuthorityReference']
                        ?? $locked->special_authority_reference,
                    'special_authority_type_code' => $validated['specialAuthorityTypeCode']
                        ?? $locked->special_authority_type_code,
                    'special_authority_date' => $validated['specialAuthorityDate']
                        ?? $locked->special_authority_date,
                    'special_authority_approved_by' => $validated['specialAuthorityApprovedBy']
                        ?? $locked->special_authority_approved_by,
                ];
            }
            $locked->fill([
                ...$attributes,
                'engagement_code' => $validated['engagementCode'] ?: $locked->engagement_code,
                'updated_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->syncCoverage($locked, $validated);

            $newValues = $this->auditSnapshot($locked);
            $this->support->event(
                $request,
                $locked,
                'UPDATE',
                $locked->status,
                $locked->status,
                $oldValues,
                $newValues,
            );
            $this->support->audit(
                $request,
                'aems.engagement.updated',
                $locked,
                $oldValues,
                $newValues,
            );

            return $locked;
        });
    }

    public function relinkIapSource(AuditEngagement $engagement): void
    {
        if ($engagement->iap_plan_engagement_id) {
            $this->iap->relink(
                $engagement->iap_plan_engagement_id,
                $engagement->id,
            );
        }
    }

    private function assertImportable(IapPlanEngagement $source): void
    {
        if (! $source->plan
            || ! in_array($source->plan->status, ['APPROVED', 'ACTIVE'], true)
            || ! $source->plan->is_active
            || $source->schedule_status === 'CANCELLED'
            || $source->offices->isEmpty()
            || $source->auditAreas->isEmpty()) {
            throw ValidationException::withMessages([
                'iapPlanEngagementId' => [
                    'Only active engagement items from an approved or active IAP with office and audit-area coverage may be imported.',
                ],
            ]);
        }
    }

    /** @param array<string, mixed> $validated */
    private function mutableAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'audit_type_id' => $validated['auditTypeId'] ?? null,
            'engagement_approach_id' => $validated['engagementApproachId'] ?? null,
            'background' => $validated['background'] ?? null,
            'objectives' => $validated['objectives'],
            'scope' => $validated['scope'],
            'exclusions' => $validated['exclusions'] ?? null,
            'planned_start_date' => $validated['plannedStartDate'] ?? null,
            'planned_end_date' => $validated['plannedEndDate'] ?? null,
            'expected_report_date' => $validated['expectedReportDate'] ?? null,
            'planned_person_days' => $validated['plannedPersonDays'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function validateCoverage(array $validated): void
    {
        $officeIds = collect($validated['officeIds'])->map(fn ($id): int => (int) $id);
        $this->assertSingleEngagementOffice($officeIds->all());
        $areaIds = collect($validated['auditAreaIds'])->map(fn ($id): int => (int) $id);
        $coveredAreaIds = DB::table('audit_area_office')
            ->whereIn('office_id', $officeIds)
            ->whereIn('audit_area_id', $areaIds)
            ->pluck('audit_area_id')
            ->unique();
        if ($areaIds->diff($coveredAreaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'auditAreaIds' => [
                    'Every audit area must be linked to at least one selected office.',
                ],
            ]);
        }
        $focusIds = collect($validated['auditFocusIds'] ?? [])->map(fn ($id): int => (int) $id);
        if ($focusIds->isNotEmpty()
            && AuditFocus::query()
                ->whereIn('id', $focusIds)
                ->whereNotIn('audit_area_id', $areaIds)
                ->exists()) {
            throw ValidationException::withMessages([
                'auditFocusIds' => ['Every audit focus must belong to a selected audit area.'],
            ]);
        }
    }

    /** @param array<string, mixed> $validated */
    private function syncCoverage(AuditEngagement $engagement, array $validated): void
    {
        $officeId = $this->assertSingleEngagementOffice($validated['officeIds']);
        $engagement->forceFill(['engagement_office_id' => $officeId])->save();
        $engagement->offices()->sync(
            [$officeId => ['is_primary' => true]],
        );
        $engagement->auditAreas()->sync($validated['auditAreaIds']);
        $engagement->auditFocuses()->sync($validated['auditFocusIds'] ?? []);
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(IapPlanEngagement $source, User $actor): array
    {
        $item = $source->prioritizationItem;
        $risk = $source->universeRiskAssessment ?? $item?->riskAssessment;
        $universe = $source->auditUniverseItem;

        return [
            'schemaVersion' => 1,
            'capturedAt' => now()->toISOString(),
            'capturedBy' => $actor->id,
            'sourceType' => 'PLANNED',
            'plan' => [
                'id' => $source->plan->id,
                'code' => $source->plan->plan_code,
                'title' => $source->plan->title,
                'fiscalYear' => $source->plan->fiscal_year,
                'status' => $source->plan->status,
                'revisionNumber' => $source->plan->revision_number,
                'approvedAt' => $source->plan->approved_at?->toISOString(),
                'approvedBy' => $source->plan->approved_by,
            ],
            'planEngagement' => [
                'id' => $source->id,
                'code' => $source->engagement_code,
                'title' => $source->title,
                'background' => $source->background,
                'objectives' => $source->objectives,
                'scope' => $source->scope,
                'exclusions' => $source->exclusions,
                'auditCriteria' => $source->audit_criteria,
                'methodology' => $source->proposed_methodology,
                'plannedStartDate' => $source->planned_start_date?->toDateString(),
                'plannedEndDate' => $source->planned_end_date?->toDateString(),
                'expectedReportDate' => $source->expected_report_date?->toDateString(),
                'plannedPersonDays' => (float) $source->estimated_person_days,
                'targetQuarter' => $source->target_quarter,
            ],
            'prioritization' => $item ? [
                'runId' => $item->prioritization_run_id,
                'runCode' => $item->run?->run_code,
                'runName' => $item->run?->name,
                'status' => $item->run?->status,
                'finalizedAt' => $item->run?->finalized_at?->toISOString(),
                'itemId' => $item->id,
                'finalRank' => $item->final_rank,
                'decision' => $item->decision,
                'decisionReason' => $item->decision_reason,
                'priorityScore' => (float) $item->priority_score,
            ] : null,
            'riskAssessment' => $risk ? [
                'id' => $risk->id,
                'periodId' => $risk->period_id,
                'periodCode' => $risk->period?->period_code
                    ?? $item?->run?->riskPeriod?->period_code,
                'status' => $risk->status,
                'assessmentDate' => $risk->assessment_date?->toDateString(),
                'inherentRiskScore' => (float) $risk->inherent_risk_score,
                'controlEffectivenessPercent' => (float) $risk->control_effectiveness_percent,
                'residualRiskScore' => (float) $risk->residual_risk_score,
                'inherentRiskLevel' => $risk->inherentRiskLevel?->code,
                'residualRiskLevel' => $risk->residualRiskLevel?->code,
                'justification' => $risk->justification,
                'scores' => $risk->scores->map(fn ($score): array => [
                    'criterionCode' => $score->criterion?->code,
                    'criterionLabel' => $score->criterion?->label,
                    'rating' => (float) $score->rating,
                    'weightedScore' => (float) $score->weighted_score,
                    'justification' => $score->justification ?? $score->comment,
                ])->values()->all(),
            ] : null,
            'auditUniverse' => $universe ? [
                'id' => $universe->id,
                'code' => $universe->subject_code,
                'name' => $universe->name,
                'description' => $universe->description,
                'auditScope' => $universe->audit_scope,
                'materialityExposure' => $universe->materiality_exposure,
                'lastAuditDate' => $universe->last_audit_date?->toDateString(),
                'responsibleOfficeId' => $universe->responsible_office_id,
                'primaryAuditAreaId' => $universe->primary_audit_area_id,
            ] : null,
            'coverage' => [
                'offices' => $source->offices->map->only(['id', 'code', 'name'])->values()->all(),
                'auditAreas' => $source->auditAreas->map->only(['id', 'code', 'name'])->values()->all(),
                'auditFocuses' => $source->auditFocuses->map->only(['id', 'code', 'name'])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(AuditEngagement $engagement): array
    {
        $engagement->loadMissing(['offices:id,code,name', 'auditAreas:id,code,name', 'auditFocuses:id,code,name']);

        return [
            'engagementCode' => $engagement->engagement_code,
            'title' => $engagement->title,
            'sourceType' => $engagement->source_type,
            'status' => $engagement->status,
            'phase' => $engagement->phase,
            'administrativeStatus' => $engagement->administrative_status,
            'objectives' => $engagement->objectives,
            'scope' => $engagement->scope,
            'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
            'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
            'expectedReportDate' => $engagement->expected_report_date?->toDateString(),
            'plannedPersonDays' => (float) $engagement->planned_person_days,
            'officeIds' => $engagement->offices->pluck('id')->all(),
            'engagementOfficeId' => $engagement->engagement_office_id,
            'auditAreaIds' => $engagement->auditAreas->pluck('id')->all(),
            'auditFocusIds' => $engagement->auditFocuses->pluck('id')->all(),
            'lockVersion' => $engagement->lock_version,
            'isActive' => $engagement->is_active,
        ];
    }

    /** @param list<int|string> $officeIds */
    private function assertSingleEngagementOffice(array $officeIds, string $label = 'Engagement'): int
    {
        $officeIds = collect($officeIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($officeIds->count() !== 1) {
            throw ValidationException::withMessages([
                'officeIds' => ["{$label} must contain exactly one Engagement Office."],
            ]);
        }

        return (int) $officeIds->first();
    }

    private function nextCode(int $year): string
    {
        $sequence = AuditEngagement::withTrashed()
            ->where('engagement_code', 'like', "AEMS-{$year}-%")
            ->count() + 1;

        do {
            $code = sprintf('AEMS-%d-%03d', $year, $sequence++);
        } while (AuditEngagement::withTrashed()->where('engagement_code', $code)->exists());

        return $code;
    }
}
