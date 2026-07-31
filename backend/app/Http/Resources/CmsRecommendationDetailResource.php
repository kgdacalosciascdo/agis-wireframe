<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Complete safe CMS-2A workspace assembled from case, intake, and lineage snapshots. */
class CmsRecommendationDetailResource extends CmsRecommendationResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);
        $intake = $this->recommendation;
        $snapshot = $intake->source_snapshot ?? [];

        return [
            ...$base,
            'intake' => [
                'id' => $intake->id,
                'transferKey' => $intake->transfer_key,
                'transferredAt' => $intake->transferred_at?->toISOString(),
                'transferredBy' => $this->safeUser($intake->transferActor),
                'sourceSchemaVersion' => $intake->source_schema_version,
                'originalTargetDate' => $intake->original_target_implementation_date?->toDateString(),
                'responsibleOfficeSnapshot' => $intake->responsible_office_snapshot,
                'confidentialitySnapshot' => [
                    'id' => $intake->confidentiality_level_id,
                    'code' => $intake->confidentiality_code_snapshot,
                    'label' => $intake->confidentiality_label_snapshot,
                ],
                'riskSnapshot' => [
                    'id' => $intake->risk_rating_id,
                    'code' => $intake->risk_code_snapshot,
                    'label' => $intake->risk_label_snapshot,
                ],
            ],
            'sourceLineage' => [
                'engagement' => [
                    'id' => $intake->audit_engagement_id,
                    'code' => data_get($snapshot, 'engagement.code'),
                    'title' => data_get($snapshot, 'engagement.title'),
                ],
                'finding' => [
                    'id' => $intake->audit_finding_id,
                    'code' => data_get($snapshot, 'finding.code'),
                    'title' => data_get($snapshot, 'finding.title'),
                ],
                'recommendation' => [
                    'id' => $intake->source_audit_recommendation_id,
                    'code' => $intake->recommendation_code,
                    'wording' => data_get($snapshot, 'recommendation.wording'),
                ],
                'report' => [
                    'id' => $intake->audit_report_id,
                    'finalReportNumber' => $intake->report_code_snapshot,
                    'versionId' => $intake->audit_report_version_id,
                    'versionNumber' => $intake->report_version_number_snapshot,
                    'issuedAt' => $intake->report_issued_at?->toISOString(),
                    'checksumSha256' => $intake->report_checksum_sha256,
                ],
            ],
            'officeAccountability' => [
                'leadResponsibleOffice' => $this->leadResponsibleOffice?->only([
                    'id', 'code', 'name', 'acronym',
                ]),
                'originalResponsibleOffices' => $intake->responsible_office_snapshot,
            ],
            'assignments' => CmsRecommendationAssignmentResource::collection(
                $this->whenLoaded('assignments'),
            ),
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(
                fn ($event): array => [
                    'id' => $event->id,
                    'eventCode' => $event->event_code,
                    'sourceModule' => $event->source_module,
                    'previousStatus' => $event->previous_status,
                    'newStatus' => $event->new_status,
                    'metadata' => $event->event_metadata,
                    'createdAt' => $event->created_at?->toISOString(),
                    'actor' => $this->safeUser($event->actor),
                ],
            )->values()),
            'actionPlanSummary' => $this->whenLoaded('actionPlan', function () use ($request): array {
                $plan = $this->actionPlan;
                if (! $plan) {
                    return [
                        'hasActionPlan' => false,
                        'permittedActions' => [],
                    ];
                }
                $current = $plan->currentVersion;
                $accepted = $plan->acceptedVersion;
                if ($request->user()->hasRole('read_only')
                    && $current
                    && ! in_array($current->status_code, [
                        'SUBMITTED',
                        'UNDER_REVIEW',
                        'ACCEPTED',
                    ], true)) {
                    $current = null;
                }

                return [
                    'hasActionPlan' => true,
                    'actionPlanId' => $plan->id,
                    'currentVersionId' => $plan->current_version_id,
                    'currentVersionNumber' => $current?->version_number,
                    'currentVersionStatus' => $current?->status_code,
                    'acceptedVersionId' => $plan->accepted_version_id,
                    'acceptedVersionNumber' => $accepted?->version_number,
                    'milestoneCount' => $current?->milestones?->count() ?? 0,
                    'latestSubmittedAt' => $current?->submitted_at?->toISOString(),
                    'latestAcceptedAt' => $accepted?->accepted_at?->toISOString(),
                    'permittedActions' => $current?->getAttribute('available_actions') ?? [],
                ];
            }),
            'progressUpdateSummary' => $this->whenLoaded(
                'progressUpdates',
                function () use ($request): array {
                    $visibleStatuses = $request->user()->hasRole('read_only')
                        ? ['SUBMITTED', 'UNDER_REVIEW', 'RECORDED']
                        : ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'RECORDED'];
                    $updates = $this->progressUpdates->filter(
                        fn ($update): bool => $update->currentVersion
                            && in_array(
                                $update->currentVersion->status_code,
                                $visibleStatuses,
                                true,
                            ),
                    )->values();
                    $latest = $updates->first();
                    $latestRecorded = $updates->first(
                        fn ($update): bool => $update->recordedVersion !== null,
                    );
                    $percentage = $latest?->currentVersion
                        ?->management_reported_overall_percentage;

                    return [
                        'hasProgressUpdates' => $updates->isNotEmpty(),
                        'latestProgressUpdateId' => $latest?->id,
                        'latestReportingPeriodEnd' => $latest
                            ?->reporting_period_end
                            ?->toDateString(),
                        'latestCurrentVersionStatus' => $latest
                            ?->currentVersion
                            ?->status_code,
                        'latestRecordedVersionId' => $latestRecorded?->recorded_version_id,
                        'latestManagementReportedPercentage' => $percentage,
                        'managementReportsComplete' => $percentage !== null
                            && (float) $percentage >= 100,
                        'reportedCompleteAwaitingValidation' => $percentage !== null
                            && (float) $percentage >= 100,
                        'evidenceLinkCount' => $latest
                            ?->currentVersion
                            ?->activeEvidenceLinks
                            ?->count() ?? 0,
                        'updatesAwaitingReview' => $updates->filter(
                            fn ($update): bool => in_array(
                                $update->currentVersion?->status_code,
                                ['SUBMITTED', 'UNDER_REVIEW'],
                                true,
                            ),
                        )->count(),
                        'permittedActions' => $latest
                            ?->currentVersion
                            ?->getAttribute('available_actions') ?? [],
                        'notIndependentlyValidated' => true,
                    ];
                },
            ),
            'validationSummary' => $this->whenLoaded(
                'validationReviews',
                function () use ($request): array {
                    $restrictedRead = $request->user()->hasRole([
                        'read_only',
                        'auditee_representative',
                    ]);
                    $reviews = $restrictedRead
                        ? $this->validationReviews->filter(
                            fn ($review): bool => $review->finalizedVersion !== null,
                        )->values()
                        : $this->validationReviews;
                    $latest = $reviews->first();
                    $current = $latest?->currentVersion;
                    $finalized = $latest?->finalizedVersion;

                    return [
                        'hasValidationReviews' => $reviews->isNotEmpty(),
                        'latestValidationReviewId' => $latest?->id,
                        'latestValidationVersionStatus' => $current?->status_code,
                        'currentAssignedValidator' => $latest?->currentAssignment?->user
                            ? $this->safeUser($latest->currentAssignment->user)
                            : null,
                        'latestProposedConclusion' => $restrictedRead
                            ? null
                            : $current?->proposed_conclusion_code,
                        'latestFinalizedConclusion' => $finalized?->final_conclusion_code,
                        'latestFinalizedAt' => $finalized?->finalized_at?->toISOString(),
                        'awaitingSupervisoryReview' => $current?->status_code === 'SUBMITTED',
                        'returnedForRevision' => $restrictedRead
                            ? false
                            : $current?->status_code === 'RETURNED',
                        'latestValidatedCompletionPercentage' => $finalized
                            ?->validated_completion_percentage,
                        'permittedActions' => $restrictedRead
                            ? []
                            : $current?->getAttribute('available_actions') ?? [],
                        'implementedDoesNotMeanClosed' => $finalized
                            ?->final_conclusion_code === 'IMPLEMENTED',
                    ];
                },
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function safeUser(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
