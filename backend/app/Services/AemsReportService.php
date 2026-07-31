<?php

namespace App\Services;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportReviewComment;
use App\Models\AuditReportVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ExitConference;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\ReportRecipient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Implements immutable draft/final audit report generation and issuance. */
class AemsReportService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly CmsRecommendationGateway $cms,
        private readonly AemsNotificationService $notifications,
        private readonly AemsEngagementTransitionService $engagementTransitions,
    ) {}

    /** @return list<array<string, mixed>> */
    public function engagements(Request $request): array
    {
        $user = $request->user();
        $query = $user->hasPermission('aems.report.view')
            && ($user->hasRole('cias_management') || $user->hasRole('agis_user'))
            ? AuditEngagement::query()->visibleTo($user)
            : AuditEngagement::query()->whereHas(
                'reports',
                fn ($reports) => $reports->visibleTo($user),
            );

        return $query->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get(['id', 'engagement_code', 'title', 'status'])
            ->map(fn (AuditEngagement $engagement): array => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ])->all();
    }

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $user = $request->user();
        $canViewInternal = $user->hasPermission('aems.report.view')
            && ($user->hasRole('cias_management') || $user->hasRole('agis_user'));
        if ($canViewInternal) {
            $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.report.view',
            );
            $report = AuditReport::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_active', true)
                ->first();
        } else {
            $report = AuditReport::query()
                ->visibleTo($user)
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_active', true)
                ->first();
            abort_unless($report, 403, 'No issued report from this engagement is visible to you.');
        }

        $findings = $canViewInternal
            ? AuditFinding::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->whereIn('status', [
                    'VALIDATED',
                    'COMMUNICATED',
                    'AWAITING_MANAGEMENT_RESPONSE',
                    'UNDER_DIALOGUE',
                    'FINALIZED',
                ])
                ->with(['responsibleOffice', 'riskRating', 'recommendations.responsibleOffice'])
                ->orderBy('finding_code')
                ->get()
                ->map(fn (AuditFinding $finding): array => $this->findingReference($finding))
                ->all()
            : [];

        $officeIds = $engagement->offices()->pluck('offices.id');
        $teamUserIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id');

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'report' => $report
                ? $this->reportData($this->load($report), $user)
                : null,
            'references' => [
                'findings' => $findings,
                'confidentialityLevels' => $canViewInternal
                    ? $this->masterItems('DOCUMENT_CONFIDENTIALITY')
                    : [],
                'users' => $canViewInternal
                    ? User::query()
                        ->where('is_active', true)
                        ->where(function ($query) use ($officeIds, $teamUserIds, $user): void {
                            $query->whereIn('office_id', $officeIds)
                                ->orWhereIn('id', $teamUserIds)
                                ->orWhere('id', $user->id);
                        })
                        ->orderBy('name')
                        ->get()
                        ->map(fn (User $member): array => $this->userData($member))
                        ->all()
                    : [],
                'offices' => $canViewInternal
                    ? Office::query()
                        ->whereIn('id', $officeIds)
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Office $office): array => $this->officeData($office))
                        ->all()
                    : [],
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createDraft(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): AuditReport {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.create',
        );
        if (! in_array($engagement->status, ['FINDINGS_COMMUNICATION', 'REPORTING'], true)) {
            throw ValidationException::withMessages([
                'engagement' => ['Reports can be prepared during findings communication or reporting.'],
            ]);
        }
        if (AuditReport::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'report' => ['This engagement already has an active report family.'],
            ]);
        }
        $findings = $this->validatedFindings(
            $engagement,
            $attributes['findingIds'],
            false,
        );
        $this->ensureConfidentiality($attributes['confidentialityLevelId']);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $attributes,
            $findings,
        ): AuditReport {
            $report = AuditReport::query()->create([
                'audit_engagement_id' => $engagement->id,
                'report_code' => $this->nextCode($engagement),
                'title' => trim($attributes['title']),
                'report_stage' => 'DRAFT_REPORT',
                'status' => 'DRAFT',
                'current_version_number' => 0,
                'confidentiality_level_id' => $attributes['confidentialityLevelId'],
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $document = $this->createDocument($request, $report);
            $report->forceFill(['document_id' => $document->id])->save();
            $version = $this->createVersion(
                $request,
                $engagement,
                $report,
                'DRAFT_REPORT',
                $attributes,
                $findings,
                [],
            );
            $report->forceFill([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
                'lock_version' => $report->lock_version + 1,
            ])->save();
            $report = $this->load($report);
            $this->record($request, $engagement, $report, 'aems.report.created', null, 'DRAFT');

            return $report;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        array $attributes,
    ): AuditReport {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.create',
        );
        $findings = $this->validatedFindings(
            $engagement,
            $attributes['findingIds'],
            $report->report_stage === 'FINAL_REPORT',
        );
        $this->ensureConfidentiality($attributes['confidentialityLevelId']);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $attributes,
            $findings,
        ): AuditReport {
            $report = $this->lock($engagement, $report, $attributes['lockVersion']);
            if (! in_array($report->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages([
                    'report' => ['A new version can only be generated from Draft or Returned status.'],
                ]);
            }
            if ((int) $report->prepared_by !== (int) $request->user()->id
                && ! $request->user()->hasRole('cias_management')) {
                throw ValidationException::withMessages([
                    'report' => ['Only the report preparer can generate its revision.'],
                ]);
            }
            $old = $this->reportAudit($report);
            $recipients = $report->report_stage === 'FINAL_REPORT'
                ? $this->validateRecipients($engagement, $attributes['recipients'])
                : [];
            $report->fill([
                'title' => trim($attributes['title']),
                'confidentiality_level_id' => $attributes['confidentialityLevelId'],
                'approving_authority' => $report->report_stage === 'FINAL_REPORT'
                    ? trim($attributes['approvingAuthority'])
                    : null,
                'status' => $report->status === 'RETURNED_FOR_REVISION'
                    ? 'RESUBMITTED'
                    : 'DRAFT',
                'lock_version' => $report->lock_version + 1,
            ])->save();
            $version = $this->createVersion(
                $request,
                $engagement,
                $report,
                $report->report_stage,
                $attributes,
                $findings,
                $recipients,
            );
            $report->forceFill([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
            ])->save();
            $report = $this->load($report);
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.version_generated',
                $old['status'],
                $report->status,
                $old,
                $attributes['changeReason'],
                [$version->document_version_id],
            );

            return $report;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createFinal(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        array $attributes,
    ): AuditReport {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.create',
        );
        $findings = $this->validatedFindings($engagement, $attributes['findingIds'], true);
        $this->ensureConfidentiality($attributes['confidentialityLevelId']);
        $recipients = $this->validateRecipients($engagement, $attributes['recipients']);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $attributes,
            $findings,
            $recipients,
        ): AuditReport {
            $report = $this->lock($engagement, $report, $attributes['lockVersion']);
            if ($report->report_stage !== 'DRAFT_REPORT' || $report->status !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'report' => ['The approved Draft Report is required before generating the Final Report.'],
                ]);
            }
            $old = $this->reportAudit($report);
            $report->fill([
                'title' => trim($attributes['title']),
                'report_stage' => 'FINAL_REPORT',
                'status' => 'DRAFT',
                'confidentiality_level_id' => $attributes['confidentialityLevelId'],
                'approving_authority' => trim($attributes['approvingAuthority']),
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'lock_version' => $report->lock_version + 1,
            ])->save();
            $version = $this->createVersion(
                $request,
                $engagement,
                $report,
                'FINAL_REPORT',
                $attributes,
                $findings,
                $recipients,
            );
            $report->forceFill([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
            ])->save();
            $report = $this->load($report);
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.final_generated',
                $old['status'],
                'DRAFT',
                $old,
                $attributes['changeReason'],
                [$version->document_version_id],
            );

            return $report;
        });
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        string $action,
        int $lockVersion,
        ?string $comment,
        ?string $issuanceDate,
    ): AuditReport {
        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $action,
            $lockVersion,
            $comment,
            $issuanceDate,
        ): AuditReport {
            $report = $this->lock($engagement, $report, $lockVersion);
            $old = $this->reportAudit($report);
            $version = $report->currentVersion()->with([
                'findings.recommendations',
                'recipients',
            ])->firstOrFail();

            if ($action === 'SUBMIT') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.create',
                );
                if (! in_array($report->status, ['DRAFT', 'RESUBMITTED'], true)) {
                    $this->invalidTransition($action, $report);
                }
                $report->fill([
                    'status' => 'PENDING_REVIEW',
                    'submitted_at' => now(),
                    'submitted_by' => $request->user()->id,
                    'lock_version' => $report->lock_version + 1,
                ])->save();
            } elseif ($action === 'RETURN') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.review',
                    $report->prepared_by,
                );
                if ($report->status !== 'PENDING_REVIEW' || ! $comment) {
                    $this->invalidTransition($action, $report);
                }
                $this->reviewComment($request, $report, $version, 'RETURNED', $comment);
                $report->fill([
                    'status' => 'RETURNED_FOR_REVISION',
                    'lock_version' => $report->lock_version + 1,
                ])->save();
            } elseif ($action === 'APPROVE') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.approve',
                    $report->prepared_by,
                );
                if (! in_array($report->status, ['PENDING_REVIEW', 'RESUBMITTED'], true)) {
                    $this->invalidTransition($action, $report);
                }
                if ($report->report_stage === 'FINAL_REPORT') {
                    $this->validateFinalApproval($engagement, $report, $version);
                }
                $this->reviewComment(
                    $request,
                    $report,
                    $version,
                    'APPROVED',
                    $comment ?: 'Approved for the next controlled stage.',
                );
                $report->fill([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'lock_version' => $report->lock_version + 1,
                ])->save();
            } elseif ($action === 'ISSUE') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.issue',
                    $report->prepared_by,
                );
                if ($report->report_stage !== 'FINAL_REPORT' || $report->status !== 'APPROVED') {
                    $this->invalidTransition($action, $report);
                }
                $this->validateFinalApproval($engagement, $report, $version);
                $issuedAt = $issuanceDate ? Carbon::parse($issuanceDate) : now();
                $version->recipients()->update([
                    'delivery_status' => 'SENT',
                    'sent_at' => $issuedAt,
                    'updated_at' => now(),
                ]);
                $version->forceFill([
                    'is_locked' => true,
                    'locked_at' => now(),
                    'locked_by' => $request->user()->id,
                ])->save();
                $report->fill([
                    'status' => 'ISSUED',
                    'issued_at' => $issuedAt,
                    'issued_by' => $request->user()->id,
                    'lock_version' => $report->lock_version + 1,
                ])->save();
                $this->transferRecommendations(
                    $request,
                    $engagement,
                    $report,
                    $version,
                    false,
                );
                $this->engagementTransitions->synchronizeIssuedReport($request, $engagement);
                $this->notifications->reportIssued(
                    $request,
                    $engagement,
                    $report,
                    $version,
                );
            } else {
                $this->invalidTransition($action, $report);
            }

            $report = $this->load($report);
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.'.strtolower($action),
                $old['status'],
                $report->status,
                $old,
                $comment,
                [$version->document_version_id],
            );
            if (in_array($action, ['SUBMIT', 'RETURN', 'APPROVE'], true)) {
                $this->notifications->controlledDocumentTransition(
                    $request,
                    $engagement,
                    'REPORT',
                    $report->id,
                    $report->report_code,
                    $report->report_stage === 'FINAL_REPORT'
                        ? 'Final Audit Report'
                        : 'Draft Audit Report',
                    $action,
                    $version->version_number,
                    $report->prepared_by,
                    $report->submitted_by,
                    'aems.report.review',
                    "/audit-engagement-management/reports?engagementId={$engagement->id}",
                );
            }

            return $report;
        });
    }

    /** @return list<array<string, mixed>> */
    public function retryCmsTransfer(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        int $lockVersion,
    ): array {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.issue',
            $report->prepared_by,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $lockVersion,
        ): array {
            $report = $this->lock($engagement, $report, $lockVersion);
            if ($report->report_stage !== 'FINAL_REPORT' || $report->status !== 'ISSUED') {
                throw ValidationException::withMessages([
                    'report' => ['Recommendations transfer only from an issued Final Report.'],
                ]);
            }
            $version = AuditReportVersion::query()
                ->lockForUpdate()
                ->with('findings.recommendations')
                ->findOrFail($report->current_version_id);

            return $this->transferRecommendations(
                $request,
                $engagement,
                $report,
                $version,
                true,
            );
        });
    }

    public function download(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): DocumentVersion {
        $this->access->authorizeReportView($request->user(), $report);
        $this->ensureReport($engagement, $report);
        if ((int) $version->audit_report_id !== (int) $report->id) {
            throw ValidationException::withMessages([
                'version' => ['The report version does not belong to this report.'],
            ]);
        }
        $isInternal = $request->user()->hasPermission('aems.report.view')
            && ($request->user()->hasRole('cias_management') || $request->user()->hasRole('agis_user'));
        if (! $isInternal && (int) $version->id !== (int) $report->current_version_id) {
            abort(403, 'Recipients can download only the issued report version.');
        }
        $this->authorizeConfidentiality($request->user(), $report, $version);
        $documentVersion = $version->documentVersion;
        if (! $documentVersion || ! Storage::disk('local')->exists($documentVersion->storage_path)) {
            abort(404, 'The generated report file is unavailable.');
        }
        $this->record(
            $request,
            $engagement,
            $report,
            'aems.report.downloaded',
            $report->status,
            $report->status,
            null,
            "Version {$version->version_number}",
            [$documentVersion->id],
        );

        return $documentVersion;
    }

    /** @return array<string, mixed> */
    public function reportData(AuditReport $report, ?User $viewer = null): array
    {
        $report->loadMissing($this->relations());

        return [
            'id' => $report->id,
            'reportCode' => $report->report_code,
            'title' => $report->title,
            'reportStage' => $report->report_stage,
            'status' => $report->status,
            'currentVersionNumber' => $report->current_version_number,
            'currentVersionId' => $report->current_version_id,
            'confidentialityLevel' => $report->confidentialityLevel?->only([
                'id',
                'code',
                'label',
            ]),
            'preparedBy' => $this->userData($report->preparer),
            'submittedBy' => $this->userData($report->submitter),
            'submittedAt' => $report->submitted_at?->toISOString(),
            'approvedBy' => $this->userData($report->approver),
            'approvedAt' => $report->approved_at?->toISOString(),
            'approvingAuthority' => $report->approving_authority,
            'issuedBy' => $this->userData($report->issuer),
            'issuedAt' => $report->issued_at?->toISOString(),
            'lockVersion' => $report->lock_version,
            'versions' => $report->versions
                ->map(fn (AuditReportVersion $version): array => $this->versionData($version))
                ->values()
                ->all(),
            'cmsTransfers' => $report->currentVersion?->findings
                ?->flatMap(fn (AuditFinding $finding) => $finding->recommendations)
                ->filter(fn (AuditRecommendation $recommendation) => $recommendation->cmsRecommendation)
                ->map(fn (AuditRecommendation $recommendation): array => [
                    'recommendationId' => $recommendation->id,
                    'recommendationCode' => $recommendation->recommendation_code,
                    'cmsRecommendationId' => $recommendation->cms_recommendation_id,
                    'transferKey' => $recommendation->cms_transfer_key,
                    'transferredAt' => $recommendation->transferred_to_cms_at?->toISOString(),
                ])->values()->all() ?? [],
            'canDownloadCurrent' => $viewer
                ? $this->canDownload($viewer, $report)
                : true,
        ];
    }

    /** @return array<string, mixed> */
    private function versionData(AuditReportVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'reportStage' => $version->report_stage,
            'contentSnapshot' => $version->content_snapshot,
            'documentVersionId' => $version->document_version_id,
            'checksumSha256' => $version->checksum_sha256,
            'pdfFileName' => $version->pdf_file_name,
            'fileSize' => $version->file_size,
            'isLocked' => $version->is_locked,
            'lockedAt' => $version->locked_at?->toISOString(),
            'changeReason' => $version->change_reason,
            'createdBy' => $this->userData($version->creator),
            'createdAt' => $version->created_at?->toISOString(),
            'findings' => $version->findings
                ->map(fn (AuditFinding $finding): array => [
                    ...$this->findingReference($finding),
                    'sequenceNumber' => (int) $finding->pivot->sequence_number,
                ])->values()->all(),
            'recipients' => $version->recipients
                ->map(fn (ReportRecipient $recipient): array => $this->recipientData($recipient))
                ->values()->all(),
            'reviewComments' => $version->reviewComments
                ->map(fn (AuditReportReviewComment $review): array => [
                    'id' => $review->id,
                    'action' => $review->review_action,
                    'comment' => $review->comment,
                    'reviewedBy' => $this->userData($review->reviewer),
                    'reviewedAt' => $review->reviewed_at?->toISOString(),
                ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $attributes
     * @param  Collection<int, AuditFinding>  $findings
     * @param  list<array<string, mixed>>  $recipients
     */
    private function createVersion(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        string $stage,
        array $attributes,
        $findings,
        array $recipients,
    ): AuditReportVersion {
        $versionNumber = $report->versions()->max('version_number') + 1;
        $snapshot = [
            'title' => trim($attributes['title']),
            'reportStage' => $stage,
            'executiveSummary' => trim($attributes['executiveSummary']),
            'sections' => collect($attributes['sections'])->values()->map(
                fn (array $section, int $index): array => [
                    'sequenceNumber' => $index + 1,
                    'title' => trim($section['title']),
                    'content' => trim($section['content']),
                ],
            )->all(),
            'findings' => $findings->values()->map(
                fn (AuditFinding $finding, int $index): array => [
                    'sequenceNumber' => $index + 1,
                    'id' => $finding->id,
                    'findingCode' => $finding->finding_code,
                    'title' => $finding->title,
                    'status' => $finding->status,
                    'criteria' => $finding->criteria,
                    'condition' => $finding->condition,
                    'cause' => $finding->cause,
                    'effect' => $finding->effect,
                    'riskRating' => $finding->riskRating?->only(['id', 'code', 'label']),
                    'responsibleOffice' => $this->officeData($finding->responsibleOffice),
                    'recommendations' => $finding->recommendations->map(
                        fn (AuditRecommendation $recommendation): array => [
                            'id' => $recommendation->id,
                            'recommendationCode' => $recommendation->recommendation_code,
                            'recommendation' => $recommendation->recommendation,
                            'responsibleOffice' => $this->officeData($recommendation->responsibleOffice),
                            'targetImplementationDate' => $recommendation
                                ->target_implementation_date?->toDateString(),
                            'status' => $recommendation->status,
                        ],
                    )->values()->all(),
                ],
            )->all(),
            'approvingAuthority' => $stage === 'FINAL_REPORT'
                ? trim($attributes['approvingAuthority'])
                : null,
            'confidentialityLevelId' => $attributes['confidentialityLevelId'],
            'recipients' => $recipients,
            'generatedAt' => now()->toISOString(),
            'generatedBy' => $this->userData($request->user()),
        ];
        $pdf = Pdf::loadView('reports.audit-report', [
            'engagement' => $engagement,
            'report' => $report,
            'versionNumber' => $versionNumber,
            'snapshot' => $snapshot,
            'configuration' => $this->runtime->publicValues(),
        ])->setPaper('a4')->output();
        $fileName = "{$report->report_code}-v{$versionNumber}.pdf";
        $path = "aems/engagements/{$engagement->id}/reports/".Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $checksum = hash('sha256', $pdf);
        $documentVersion = $report->document->versions()->create([
            'version_number' => $versionNumber,
            'version_label' => "{$stage} version {$versionNumber}",
            'change_summary' => $attributes['changeReason'] ?? 'Initial generated report version.',
            'original_file_name' => $fileName,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($pdf),
            'checksum_sha256' => $checksum,
            'uploaded_by' => $request->user()->id,
        ]);
        $report->document->forceFill([
            'confidentiality_level_id' => $attributes['confidentialityLevelId'],
            'current_version_id' => $documentVersion->id,
            'version' => $documentVersion->version_label,
            'original_file_name' => $fileName,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($pdf),
            'checksum_sha256' => $checksum,
            'updated_by' => $request->user()->id,
        ])->save();
        $version = $report->versions()->create([
            'version_number' => $versionNumber,
            'report_stage' => $stage,
            'content_snapshot' => $snapshot,
            'document_version_id' => $documentVersion->id,
            'checksum_sha256' => $checksum,
            'pdf_file_name' => $fileName,
            'file_size' => strlen($pdf),
            'is_locked' => false,
            'change_reason' => $attributes['changeReason'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $version->findings()->attach($findings->values()->mapWithKeys(
            fn (AuditFinding $finding, int $index): array => [
                $finding->id => [
                    'sequence_number' => $index + 1,
                    'is_included' => true,
                ],
            ],
        )->all());
        foreach ($recipients as $recipient) {
            $version->recipients()->create($recipient);
        }

        return $version;
    }

    private function createDocument(Request $request, AuditReport $report): Document
    {
        $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $placeholder = 'pending/'.Str::uuid().'.pdf';
        $document = Document::query()->create([
            'document_type_id' => $type->id,
            'confidentiality_level_id' => $report->confidentiality_level_id,
            'title' => $report->title,
            'description' => "Generated AEMS report {$report->report_code}.",
            'owner_module' => 'AEMS',
            'library_visible' => false,
            'original_file_name' => "{$report->report_code}-pending.pdf",
            'storage_path' => $placeholder,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 0,
            'checksum_sha256' => hash('sha256', ''),
            'uploaded_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'is_active' => true,
        ]);
        $document->forceFill([
            'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
        ])->save();
        $document->links()->create([
            'module_code' => 'AEMS',
            'record_type' => 'AUDIT_REPORT',
            'record_id' => $report->id,
            'record_code' => $report->report_code,
            'record_label' => "{$report->report_code} — {$report->title}",
            'linked_by' => $request->user()->id,
        ]);

        return $document;
    }

    /** @param list<int> $findingIds
     * @return Collection<int, AuditFinding>
     */
    private function validatedFindings(
        AuditEngagement $engagement,
        array $findingIds,
        bool $finalOnly,
    ) {
        $statuses = $finalOnly
            ? ['FINALIZED']
            : ['VALIDATED', 'COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE', 'UNDER_DIALOGUE', 'FINALIZED'];
        $findings = AuditFinding::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->whereIn('status', $statuses)
            ->whereIn('id', $findingIds)
            ->with(['riskRating', 'responsibleOffice', 'recommendations.responsibleOffice'])
            ->get()->keyBy('id');
        if ($findings->count() !== count(array_unique($findingIds))) {
            throw ValidationException::withMessages([
                'findingIds' => [$finalOnly
                    ? 'Only current finalized Findings can enter the Final Report.'
                    : 'Draft Reports require current validated or later Findings from this engagement.'],
            ]);
        }

        return collect($findingIds)->map(fn (int $id) => $findings->get($id));
    }

    /** @param list<array<string, mixed>> $recipients
     * @return list<array<string, mixed>>
     */
    private function validateRecipients(AuditEngagement $engagement, array $recipients): array
    {
        $normalized = [];
        foreach ($recipients as $index => $recipient) {
            $type = $recipient['recipientType'];
            $userId = $recipient['userId'] ?? null;
            $officeId = $recipient['officeId'] ?? null;
            $externalName = $this->nullableTrim($recipient['externalName'] ?? null);
            if ($type === 'USER' && ! User::query()->where('is_active', true)->whereKey($userId)->exists()) {
                throw ValidationException::withMessages([
                    "recipients.{$index}.userId" => ['Select an active internal recipient.'],
                ]);
            }
            if ($type === 'OFFICE' && (! $officeId
                || ! $engagement->offices()->whereKey($officeId)->exists())) {
                throw ValidationException::withMessages([
                    "recipients.{$index}.officeId" => ['Select an office covered by the engagement.'],
                ]);
            }
            if ($type === 'EXTERNAL' && ! $externalName) {
                throw ValidationException::withMessages([
                    "recipients.{$index}.externalName" => ['Enter the external recipient name.'],
                ]);
            }
            $normalized[] = [
                'user_id' => $type === 'USER' ? $userId : null,
                'office_id' => $type === 'OFFICE' ? $officeId : null,
                'external_name' => $type === 'EXTERNAL' ? $externalName : null,
                'external_email' => $type === 'EXTERNAL'
                    ? $this->nullableTrim($recipient['externalEmail'] ?? null)
                    : null,
                'recipient_type' => $type,
                'delivery_method' => $recipient['deliveryMethod'] ?? 'SYSTEM',
                'delivery_status' => 'PENDING',
            ];
        }

        return $normalized;
    }

    private function validateFinalApproval(
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): void {
        if (! $report->approving_authority || ! $report->confidentiality_level_id) {
            throw ValidationException::withMessages([
                'report' => ['Final approval requires an approving authority and confidentiality level.'],
            ]);
        }
        if ($version->recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'report' => ['Final approval requires at least one version-bound recipient.'],
            ]);
        }
        if ($version->findings->isEmpty()
            || $version->findings->contains(fn ($finding) => $finding->status !== 'FINALIZED')) {
            throw ValidationException::withMessages([
                'report' => ['Only finalized Findings can be approved in the Final Report.'],
            ]);
        }
        if (! ExitConference::query()
            ->where('audit_engagement_id', $engagement->id)
            ->whereIn('status', ['COMPLETED', 'WAIVED'])
            ->exists()) {
            throw ValidationException::withMessages([
                'report' => ['Final approval requires a completed or formally waived Exit Conference.'],
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function transferRecommendations(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        bool $recordEvent,
    ): array {
        $transfers = [];
        foreach ($version->findings as $finding) {
            foreach ($finding->recommendations as $recommendation) {
                if (! in_array($recommendation->status, ['FINALIZED', 'TRANSFERRED'], true)) {
                    continue;
                }
                $cms = $this->cms->transfer(
                    $recommendation,
                    $engagement,
                    $report,
                    $version,
                    $request,
                );
                $transfers[] = [
                    'recommendationId' => $recommendation->id,
                    'recommendationCode' => $recommendation->recommendation_code,
                    'cmsRecommendationId' => $cms->id,
                    'transferKey' => $cms->transfer_key,
                    'transferredAt' => $cms->transferred_at?->toISOString(),
                ];
            }
        }
        if ($recordEvent) {
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.cms_transfer_retried',
                'ISSUED',
                'ISSUED',
                null,
                'Idempotent CMS recommendation transfer.',
                [$version->document_version_id],
            );
        }

        return $transfers;
    }

    private function reviewComment(
        Request $request,
        AuditReport $report,
        AuditReportVersion $version,
        string $action,
        string $comment,
    ): void {
        AuditReportReviewComment::query()->create([
            'audit_report_id' => $report->id,
            'audit_report_version_id' => $version->id,
            'review_action' => $action,
            'comment' => trim($comment),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
    }

    private function authorizeConfidentiality(
        User $user,
        AuditReport $report,
        AuditReportVersion $version,
    ): void {
        $directRecipient = $version->recipients()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->office_id) {
                    $query->orWhere('office_id', $user->office_id);
                }
            })->exists();
        if ($directRecipient) {
            return;
        }
        $code = $report->confidentialityLevel?->code ?? 'INTERNAL';
        $allowed = match ($code) {
            'RESTRICTED' => $user->hasPermission('documents.view_restricted'),
            'CONFIDENTIAL' => $user->hasPermission('documents.view_confidential'),
            default => true,
        };
        abort_unless($allowed, 403, 'The report confidentiality level does not allow this download.');
    }

    private function canDownload(User $user, AuditReport $report): bool
    {
        try {
            $this->access->authorizeReportView($user, $report);
            $version = $report->currentVersion;
            if (! $version) {
                return false;
            }
            $this->authorizeConfidentiality($user, $report, $version);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureConfidentiality(int $id): void
    {
        $valid = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->whereHas('items', fn ($items) => $items->whereKey($id)->where('is_active', true))
            ->exists();
        if (! $valid) {
            throw ValidationException::withMessages([
                'confidentialityLevelId' => ['Select an active confidentiality level.'],
            ]);
        }
    }

    private function lock(
        AuditEngagement $engagement,
        AuditReport $report,
        int $lockVersion,
    ): AuditReport {
        $locked = AuditReport::query()->lockForUpdate()->findOrFail($report->id);
        $this->ensureReport($engagement, $locked);
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This report changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureReport(AuditEngagement $engagement, AuditReport $report): void
    {
        if ((int) $report->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages([
                'report' => ['The report does not belong to this engagement.'],
            ]);
        }
    }

    private function invalidTransition(string $action, AuditReport $report): never
    {
        throw ValidationException::withMessages([
            'action' => ["{$action} is not allowed while the report is {$report->status}."],
        ]);
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        return sprintf('AR-%s-%02d', $engagement->engagement_code, 1);
    }

    /** @return list<array<string, mixed>> */
    private function masterItems(string $code): array
    {
        return MasterList::query()->where('code', $code)->firstOrFail()
            ->items()->where('is_active', true)->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description'])->map->toArray()->all();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'confidentialityLevel',
            'preparer',
            'submitter',
            'approver',
            'issuer',
            'currentVersion.findings.recommendations.cmsRecommendation',
            'versions.creator',
            'versions.documentVersion',
            'versions.findings.responsibleOffice',
            'versions.findings.riskRating',
            'versions.findings.recommendations.responsibleOffice',
            'versions.recipients.user',
            'versions.recipients.office',
            'versions.reviewComments.reviewer',
        ];
    }

    private function load(AuditReport $report): AuditReport
    {
        return $report->fresh($this->relations());
    }

    /** @return array<string, mixed> */
    private function findingReference(AuditFinding $finding): array
    {
        return [
            'id' => $finding->id,
            'findingCode' => $finding->finding_code,
            'title' => $finding->title,
            'status' => $finding->status,
            'riskRating' => $finding->riskRating?->only(['id', 'code', 'label']),
            'responsibleOffice' => $this->officeData($finding->responsibleOffice),
            'recommendationCount' => $finding->recommendations->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function recipientData(ReportRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'recipientType' => $recipient->recipient_type,
            'user' => $this->userData($recipient->user),
            'office' => $this->officeData($recipient->office),
            'externalName' => $recipient->external_name,
            'externalEmail' => $recipient->external_email,
            'deliveryMethod' => $recipient->delivery_method,
            'deliveryStatus' => $recipient->delivery_status,
            'sentAt' => $recipient->sent_at?->toISOString(),
            'acknowledgedAt' => $recipient->acknowledged_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function userData(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'officeId' => $user->office_id,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function officeData(?Office $office): ?array
    {
        return $office ? [
            'id' => $office->id,
            'code' => $office->code,
            'name' => $office->name,
            'acronym' => $office->acronym,
        ] : null;
    }

    /** @return array<string, mixed> */
    private function reportAudit(AuditReport $report): array
    {
        return [
            'id' => $report->id,
            'reportCode' => $report->report_code,
            'reportStage' => $report->report_stage,
            'status' => $report->status,
            'currentVersionNumber' => $report->current_version_number,
            'lockVersion' => $report->lock_version,
        ];
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?array $oldValues = null,
        ?string $comment = null,
        ?array $documentVersionIds = null,
    ): void {
        $newValues = $this->reportAudit($report);
        $this->support->event(
            $request,
            $engagement,
            $action,
            $fromStatus,
            $toStatus,
            $oldValues,
            $newValues,
            $comment,
            'AUDIT_REPORT',
            $report->id,
            $report->current_version_number,
            $report->report_code,
            null,
            $documentVersionIds,
        );
        $this->support->audit(
            $request,
            $action,
            $engagement,
            $oldValues,
            $newValues,
            [
                'subjectType' => 'AUDIT_REPORT',
                'subjectId' => $report->id,
                'subjectCode' => $report->report_code,
                'documentVersionIds' => $documentVersionIds,
            ],
        );
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
