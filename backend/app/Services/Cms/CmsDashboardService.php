<?php

namespace App\Services\Cms;

use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsValidationReview;
use App\Models\CmsValidationVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Produces live aggregates from the actor's database-scoped CMS population. */
class CmsDashboardService
{
    public function __construct(
        private readonly CmsRecommendationRegistryService $registry,
        private readonly CmsRecommendationScopeService $scope,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $base = $this->registry->baseQuery($user, 'cms.dashboard.view');
        $overdue = $this->registry->applyOverdue(clone $base, $today);
        $visibleCaseIds = (clone $base)->reorder()->pluck('cms_recommendation_cases.id');

        $cards = [
            'totalVisibleCases' => (clone $base)->count(),
            'transferredOpenCases' => (clone $base)
                ->where('cms_recommendation_cases.status_code', 'TRANSFERRED')
                ->count(),
            'assignedCases' => (clone $base)
                ->whereNotNull('cms_current_assignments.id')
                ->count(),
            'unassignedCases' => (clone $base)
                ->whereNull('cms_current_assignments.id')
                ->count(),
            'overdueCases' => (clone $overdue)->count(),
            'withoutTargetDate' => (clone $base)
                ->whereNull('cms_recommendation_cases.effective_target_implementation_date')
                ->count(),
            'transferredThisMonth' => (clone $base)
                ->whereBetween('cms_intakes.transferred_at', [
                    $now->startOfMonth(),
                    $now->endOfMonth(),
                ])->count(),
            'highRiskCases' => (clone $base)
                ->whereIn('cms_intakes.risk_code_snapshot', $this->highRiskCodes())
                ->count(),
            'highRiskOverdueCases' => (clone $overdue)
                ->whereIn('cms_intakes.risk_code_snapshot', $this->highRiskCodes())
                ->count(),
            'monitoringCasesWithoutRecordedProgress' => (clone $base)
                ->where('cms_recommendation_cases.status_code', 'MONITORING')
                ->whereDoesntHave(
                    'progressUpdates',
                    fn (Builder $updates) => $updates->whereNotNull('recorded_version_id'),
                )
                ->count(),
            'progressUpdatesAwaitingReview' => CmsProgressUpdateVersion::query()
                ->whereIn('status_code', ['SUBMITTED', 'UNDER_REVIEW'])
                ->whereHas(
                    'progressUpdate',
                    fn (Builder $updates) => $updates->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->count(),
            'recordedProgressUpdates' => CmsProgressUpdate::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->whereNotNull('recorded_version_id')
                ->count(),
            'managementReportedCompleteAwaitingValidation' => CmsProgressUpdate::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->whereHas(
                    'recordedVersion',
                    fn (Builder $version) => $version
                        ->where('management_reported_overall_percentage', '>=', 100),
                )
                ->count(),
            'casesAwaitingValidationAssignment' => CmsValidationReview::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->where('active_slot', 'ACTIVE')
                ->whereDoesntHave('currentAssignment')
                ->count(),
            'activeValidations' => CmsValidationReview::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->where('active_slot', 'ACTIVE')
                ->count(),
            'validationsAwaitingSupervisoryReview' => CmsValidationVersion::query()
                ->where('status_code', CmsValidationVersion::STATUS_SUBMITTED)
                ->whereHas(
                    'review',
                    fn (Builder $review) => $review->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->count(),
            'returnedValidations' => CmsValidationVersion::query()
                ->where('status_code', CmsValidationVersion::STATUS_RETURNED)
                ->whereHas(
                    'review',
                    fn (Builder $review) => $review->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->count(),
            'finalizedValidationConclusions' => collect([
                'NOT_IMPLEMENTED',
                'PARTIALLY_IMPLEMENTED',
                'IMPLEMENTED',
                'INADEQUATE_BASIS',
            ])->mapWithKeys(
                fn (string $conclusion): array => [
                    $conclusion => CmsValidationVersion::query()
                        ->where('status_code', CmsValidationVersion::STATUS_FINALIZED)
                        ->where('final_conclusion_code', $conclusion)
                        ->whereHas(
                            'review',
                            fn (Builder $review) => $review->whereIn(
                                'cms_recommendation_case_id',
                                $visibleCaseIds,
                            ),
                        )
                        ->count(),
                ],
            )->all(),
        ];

        return [
            'evaluationDateTime' => $now->toISOString(),
            'evaluationDate' => $today->toDateString(),
            'scope' => $this->scope->summary($user),
            'cards' => $cards,
            'groups' => [
                'byResponsibleOffice' => $this->group(
                    clone $base,
                    'cms_recommendation_cases.lead_responsible_office_id',
                    "COALESCE(cms_offices.code, 'UNASSIGNED')",
                    "COALESCE(cms_offices.name, 'No responsible office')",
                ),
                'byRiskLevel' => $this->group(
                    clone $base,
                    'cms_intakes.risk_rating_id',
                    "COALESCE(cms_intakes.risk_code_snapshot, 'UNRATED')",
                    "COALESCE(cms_intakes.risk_label_snapshot, 'Unrated')",
                ),
                'byConfidentialityLevel' => $this->group(
                    clone $base,
                    'cms_intakes.confidentiality_level_id',
                    "COALESCE(cms_intakes.confidentiality_code_snapshot, 'INTERNAL')",
                    "COALESCE(cms_intakes.confidentiality_label_snapshot, 'Internal')",
                ),
                'byAssignedMonitor' => $this->group(
                    clone $base,
                    'cms_current_assignments.user_id',
                    "COALESCE(cms_monitor_users.employee_id, 'UNASSIGNED')",
                    "COALESCE(cms_monitor_users.name, 'Unassigned')",
                ),
            ],
            'recentlyTransferred' => $this->records(
                (clone $base)
                    ->with($this->summaryRelations())
                    ->orderByDesc('cms_intakes.transferred_at')
                    ->orderByDesc('cms_recommendation_cases.id')
                    ->limit(10)
                    ->get(),
                $today,
            ),
            'oldestUnresolvedTargetDates' => $this->records(
                (clone $base)
                    ->with($this->summaryRelations())
                    ->whereNotNull(
                        'cms_recommendation_cases.effective_target_implementation_date',
                    )
                    ->whereNotIn('cms_recommendation_cases.status_code', [
                        'CLOSED', 'ACCEPTED_RISK', 'CANCELLED',
                    ])
                    ->orderBy(
                        'cms_recommendation_cases.effective_target_implementation_date',
                    )
                    ->orderBy('cms_recommendation_cases.id')
                    ->limit(10)
                    ->get(),
                $today,
            ),
            'dueSoon' => [
                'available' => false,
                'reason' => 'No approved CMS due-soon runtime threshold is configured.',
            ],
            'dataLimitations' => [
                'Due-soon metrics require an approved runtime threshold.',
                'Progress metrics remain management-reported until a separate Validation Review is finalized.',
                'Target-date extension, escalation, and recommendation closure workflows are not implemented.',
            ],
        ];
    }

    /**
     * @return list<array{id: int|null, code: string, label: string, count: int}>
     */
    private function group(
        Builder $query,
        string $idColumn,
        string $codeExpression,
        string $labelExpression,
    ): array {
        return $query
            ->reorder()
            ->selectRaw("{$idColumn} as group_id")
            ->selectRaw("{$codeExpression} as group_code")
            ->selectRaw("{$labelExpression} as group_label")
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupByRaw("{$idColumn}, {$codeExpression}, {$labelExpression}")
            ->orderByDesc('aggregate_count')
            ->orderBy('group_label')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->group_id === null ? null : (int) $row->group_id,
                'code' => (string) $row->group_code,
                'label' => (string) $row->group_label,
                'count' => (int) $row->aggregate_count,
            ])->all();
    }

    /**
     * @param  iterable<int, CmsRecommendationCase>  $cases
     * @return list<array<string, mixed>>
     */
    private function records(iterable $cases, CarbonImmutable $today): array
    {
        return collect($cases)->map(function (CmsRecommendationCase $case) use ($today): array {
            $target = $case->effective_target_implementation_date;

            return [
                'id' => $case->id,
                'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
                'recommendationCode' => $case->recommendation->recommendation_code,
                'status' => $case->status_code,
                'transferredAt' => $case->recommendation->transferred_at?->toISOString(),
                'effectiveTargetDate' => $target?->toDateString(),
                'isOverdue' => $target !== null
                    && $target->lt($today)
                    && ! in_array($case->status_code, [
                        'CLOSED', 'ACCEPTED_RISK', 'CANCELLED',
                    ], true),
                'responsibleOffice' => $case->leadResponsibleOffice?->only([
                    'id', 'code', 'name',
                ]),
                'risk' => [
                    'code' => $case->recommendation->risk_code_snapshot,
                    'label' => $case->recommendation->risk_label_snapshot,
                ],
                'assignedMonitor' => $case->currentAssignment?->user ? [
                    'id' => $case->currentAssignment->user->id,
                    'employeeId' => $case->currentAssignment->user->employee_id,
                    'name' => $case->currentAssignment->user->name,
                ] : null,
            ];
        })->values()->all();
    }

    /** @return list<string> */
    private function highRiskCodes(): array
    {
        return ['HIGH', 'VERY_HIGH', 'CRITICAL'];
    }

    /** @return list<string> */
    private function summaryRelations(): array
    {
        return [
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
        ];
    }
}
