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
