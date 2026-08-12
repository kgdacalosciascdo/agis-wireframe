<?php

namespace App\Services;

use App\Models\AemsPlanningObjective;
use App\Models\AemsPlanningPackage;
use App\Models\AemsPlanningPackageReview;
use App\Models\AemsPlanningPackageVersion;
use App\Models\AemsProcessFlowDocument;
use App\Models\AemsRiskMatrix;
use App\Models\AemsRiskMatrixItem;
use App\Models\AemsRiskWorkingPaperLink;
use App\Models\AuditEngagement;
use App\Models\AuditProgramProcedure;
use App\Models\EngagementEvent;
use App\Models\DocumentVersion;
use App\Models\WorkingPaper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Versioned planning package and risk-planning workflow for an AEMS engagement. */
class AemsPlanningPackageService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'engagementOrder', 'engagementPlan', 'planningPackage.preparer', 'planningPackage.submitter',
            'planningPackage.approver', 'planningPackage.versions.creator', 'planningPackage.versions.objectives',
            'planningPackage.versions.processFlows.processOwnerOffice',
            'planningPackage.versions.riskMatrix.items.responsibleOffice',
            'planningPackage.versions.riskMatrix.items.objectives',
            'planningPackage.versions.riskMatrix.items.procedures.program',
            'planningPackage.versions.riskMatrix.items.workingPaperLinks.workingPaper',
            'planningPackage.versions.reviews.reviewer',
        ]);
        $package = $engagement->planningPackage;
        $versions = $package?->versions ?? collect();
        $version = $versions->last();
        $program = $engagement->programs()->where('is_current_revision', true)->where('is_active', true)->latest('revision_number')->first();
        $procedures = $program?->procedures()->with('program')->get() ?? collect();

        return [
            'engagement' => ['id' => $engagement->id, 'engagementCode' => $engagement->engagement_code, 'title' => $engagement->title, 'status' => $engagement->status, 'sourceType' => $engagement->source_type],
            'lineage' => $this->lineage($engagement),
            'approvedAep' => $engagement->engagementPlan?->status === 'APPROVED',
            'approvedProgram' => (bool) ($program && in_array($program->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true)),
            'package' => $package ? $this->packageSnapshot($package, $version, $versions) : null,
            'readiness' => $version ? $this->readiness($engagement, $package, $version, $procedures) : $this->emptyReadiness(),
            'procedures' => $procedures->map(fn (AuditProgramProcedure $procedure): array => ['id' => $procedure->id, 'code' => $procedure->procedure_code, 'objective' => $procedure->objective])->values(),
            'capabilities' => ['canCreate' => ! $package, 'canEdit' => (bool) $package && in_array($package->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true), 'canReview' => (bool) $package && in_array($package->status, ['PENDING_REVIEW', 'RESUBMITTED'], true), 'canApprove' => (bool) $package && in_array($package->status, ['PENDING_REVIEW', 'RESUBMITTED'], true), 'canRevise' => $package?->status === 'APPROVED'],
        ];
    }

    /** @param array<string,mixed> $attributes */
    public function create(Request $request, AuditEngagement $engagement, array $attributes): AemsPlanningPackage
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.create');
        return DB::transaction(function () use ($request, $engagement, $attributes): AemsPlanningPackage {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            if ($locked->planningPackage()->exists()) {
                throw ValidationException::withMessages(['package' => ['This engagement already has a planning package.']]);
            }
            if ($locked->source_type === 'PLANNED' && $locked->iap_plan_engagement_id
                && AemsPlanningPackage::query()->where('iap_plan_engagement_id', $locked->iap_plan_engagement_id)->exists()) {
                throw ValidationException::withMessages(['source' => ['This approved IAP source already has an AEMS planning package.']]);
            }
            $package = AemsPlanningPackage::query()->create([
                'audit_engagement_id' => $locked->id, 'package_code' => 'APP-'.$locked->engagement_code,
                'status' => 'DRAFT', 'current_version_number' => 1, 'source_type' => $locked->source_type,
                'iap_plan_engagement_id' => $locked->iap_plan_engagement_id, 'iap_plan_id' => $locked->iap_plan_id,
                'iap_prioritization_item_id' => $locked->iap_prioritization_item_id, 'iap_risk_assessment_id' => $locked->iap_risk_assessment_id,
                'iap_audit_universe_item_id' => $locked->iap_audit_universe_item_id, 'prepared_by' => $request->user()->id,
                'lock_version' => 1, 'is_active' => true,
            ]);
            $version = $this->createVersion($request, $locked, $package, $attributes, 1, null);
            $this->support->event($request, $locked, 'PLANNING_PACKAGE_CREATE', null, 'DRAFT', null, ['versionNumber' => 1], null, 'PLANNING_PACKAGE', $package->id, 1, $package->package_code);
            $this->support->audit($request, 'aems.planning-package.created', $locked, null, $this->versionSnapshot($version), ['planningPackageId' => $package->id, 'planningPackageCode' => $package->package_code]);
            return $package->fresh();
        });
    }

    /** @param array<string,mixed> $attributes */
    public function update(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, array $attributes): AemsPlanningPackage
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.update');
        return DB::transaction(function () use ($request, $engagement, $package, $attributes): AemsPlanningPackage {
            $locked = $this->lockPackage($engagement, $package, (int) $attributes['lockVersion']);
            if (! in_array($locked->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages(['status' => ['Only a draft or returned planning package can be edited.']]);
            }
            $old = $locked->latestVersion()->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = $this->createVersion($request, $engagement, $locked, $attributes, $number, $attributes['changeReason'] ?? null);
            $locked->update(['current_version_number' => $number, 'prepared_by' => $request->user()->id, 'submitted_by' => null, 'submitted_at' => null, 'approved_by' => null, 'approved_at' => null, 'lock_version' => $locked->lock_version + 1]);
            $this->logChange($request, $engagement, $locked, $version, 'UPDATE', $old, $version, $attributes['changeReason'] ?? null);
            return $locked->fresh();
        });
    }

    public function transition(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, string $action, int $lockVersion, ?string $comment): AemsPlanningPackage
    {
        $action = strtoupper($action);
        $permissions = ['SUBMIT' => 'aems.planning-package.update', 'RESUBMIT' => 'aems.planning-package.update', 'REVIEW' => 'aems.planning-package.review', 'RETURN' => 'aems.planning-package.review', 'APPROVE' => 'aems.planning-package.approve'];
        if (! isset($permissions[$action])) throw ValidationException::withMessages(['action' => ['Unsupported planning package workflow action.']]);
        $this->access->authorizeEngagementAction($request->user(), $engagement, $permissions[$action], in_array($action, ['REVIEW', 'RETURN', 'APPROVE'], true) ? $package->prepared_by : null);
        return DB::transaction(function () use ($request, $engagement, $package, $action, $lockVersion, $comment): AemsPlanningPackage {
            $locked = $this->lockPackage($engagement, $package, $lockVersion);
            $version = $locked->latestVersion()->firstOrFail();
            $from = $locked->status;
            $to = $this->nextStatus($from, $action);
            if ($action === 'RETURN' && mb_strlen(trim((string) $comment)) < 5) throw ValidationException::withMessages(['comment' => ['A clear return instruction is required.']]);
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) $this->ensureReady($engagement, $locked, $version);
            if ($action === 'REVIEW') {
                if ((int) $request->user()->id === (int) $locked->prepared_by) throw ValidationException::withMessages(['review' => ['The preparer cannot independently review the planning package.']]);
                if (AemsPlanningPackageReview::query()->where('planning_package_id', $locked->id)->where('planning_package_version_id', $version->id)->where('reviewer_id', $request->user()->id)->exists()) {
                    throw ValidationException::withMessages(['review' => ['This reviewer has already assessed the current planning package version.']]);
                }
                AemsPlanningPackageReview::query()->create(['planning_package_id' => $locked->id, 'planning_package_version_id' => $version->id, 'reviewer_id' => $request->user()->id, 'result' => 'ACCEPTED', 'comment' => $comment, 'reviewed_at' => now()]);
            }
            if ($action === 'APPROVE') {
                $reviewed = $locked->reviews()->where('planning_package_version_id', $version->id)->where('result', 'ACCEPTED')->where('reviewer_id', '<>', $locked->prepared_by)->exists();
                if (! $reviewed) throw ValidationException::withMessages(['action' => ['The current planning package version must be independently reviewed before approval.']]);
                $this->ensureReady($engagement, $locked, $version);
            }
            $changes = ['lock_version' => $locked->lock_version + 1];
            if ($action !== 'REVIEW') $changes['status'] = $to;
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) { $changes['submitted_by'] = $request->user()->id; $changes['submitted_at'] = now(); }
            if ($action === 'APPROVE') { $changes['approved_by'] = $request->user()->id; $changes['approved_at'] = now(); $changes['approved_version_number'] = $version->version_number; }
            $locked->update($changes);
            $this->support->event($request, $engagement, 'PLANNING_PACKAGE_'.$action, $from, $to, ['status' => $from], ['status' => $to, 'versionNumber' => $version->version_number], $comment, 'PLANNING_PACKAGE', $locked->id, $version->version_number, $locked->package_code);
            $this->support->audit($request, 'aems.planning-package.'.str($action)->lower(), $engagement, ['status' => $from], ['status' => $to, 'versionNumber' => $version->version_number], ['planningPackageId' => $locked->id, 'planningPackageCode' => $locked->package_code, 'comment' => $comment]);
            $this->notifications->controlledDocumentTransition($request, $engagement, 'PLANNING_PACKAGE', $locked->id, $locked->package_code, 'Planning Package', $action, $version->version_number, $locked->prepared_by, $locked->submitted_by, 'aems.planning-package.review', "/audit-engagement-management/planning-package?engagementId={$engagement->id}");
            return $locked->fresh();
        });
    }

    public function revise(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, int $lockVersion, string $reason): AemsPlanningPackage
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.revise', $package->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $package, $lockVersion, $reason): AemsPlanningPackage {
            $locked = $this->lockPackage($engagement, $package, $lockVersion);
            if ($locked->status !== 'APPROVED') throw ValidationException::withMessages(['status' => ['Only an approved planning package can start a formal revision.']]);
            $source = $locked->latestVersion()->firstOrFail()->load(['objectives', 'processFlows', 'riskMatrix.items']);
            $number = $locked->current_version_number + 1;
            $payload = [
                'preliminarySurvey' => $source->preliminary_survey,
                'planningAttributes' => $source->planning_attributes,
                'preliminarySurveyDocumentVersionId' => $source->preliminary_survey_document_version_id,
                'objectives' => $source->objectives->map(fn ($objective) => ['code' => $objective->objective_code, 'statement' => $objective->objective_statement, 'sourceType' => $objective->source_type, 'sourceReference' => $objective->source_reference, 'sequence' => $objective->sequence])->all(),
                'processFlows' => $source->processFlows->map(fn ($flow) => ['code' => $flow->flow_code, 'title' => $flow->title, 'description' => $flow->description, 'processOwnerOfficeId' => $flow->process_owner_office_id, 'documentVersionId' => $flow->document_version_id, 'sourceType' => $flow->source_type, 'sourceReference' => $flow->source_reference, 'sequence' => $flow->sequence])->all(),
                'riskMatrix' => $source->riskMatrix ? ['code' => $source->riskMatrix->matrix_code, 'title' => $source->riskMatrix->title, 'methodology' => $source->riskMatrix->methodology, 'riskAppetite' => $source->riskMatrix->risk_appetite, 'overallConclusion' => $source->riskMatrix->overall_conclusion] : null,
                'riskItems' => $source->riskMatrix?->items->map(fn ($item) => ['riskCode' => $item->risk_code, 'riskStatement' => $item->risk_statement, 'riskCategory' => $item->risk_category, 'inherentLikelihood' => $item->inherent_likelihood, 'inherentImpact' => $item->inherent_impact, 'inherentScore' => $item->inherent_score, 'controlDescription' => $item->control_description, 'controlEffectiveness' => $item->control_effectiveness, 'residualLikelihood' => $item->residual_likelihood, 'residualImpact' => $item->residual_impact, 'residualScore' => $item->residual_score, 'residualRating' => $item->residual_rating, 'riskResponse' => $item->risk_response, 'responsibleOfficeId' => $item->responsible_office_id, 'sequence' => $item->sequence, 'status' => $item->status, 'objectiveCodes' => $item->objectives()->pluck('objective_code')->all(), 'procedureIds' => $item->procedures()->pluck('audit_program_procedures.id')->all(), 'workingPapers' => $item->workingPaperLinks->map(fn ($link) => ['workingPaperId' => $link->working_paper_id, 'reference' => $link->working_paper_reference, 'basis' => $link->relationship_basis])->all()])->all() ?? [],
            ];
            $version = $this->createVersion($request, $engagement, $locked, $payload, $number, $reason);
            $locked->update(['status' => 'DRAFT', 'current_version_number' => $number, 'prepared_by' => $request->user()->id, 'submitted_by' => null, 'submitted_at' => null, 'lock_version' => $locked->lock_version + 1]);
            $this->logChange($request, $engagement, $locked, $version, 'REVISE', $source, $version, $reason);
            return $locked->fresh();
        });
    }

    /** @return array<string,mixed> */
    public function readiness(AuditEngagement $engagement, AemsPlanningPackage $package, AemsPlanningPackageVersion $version, $procedures = null): array
    {
        $procedures ??= AuditProgramProcedure::query()->with('program')->whereHas('program', fn ($q) => $q->where('audit_engagement_id', $engagement->id)->where('is_current_revision', true)->where('is_active', true))->get();
        $survey = $version->preliminary_survey ?? [];
        $objectives = $version->objectives()->get(); $flows = $version->processFlows()->get(); $matrix = $version->riskMatrix()->with('items')->first(); $items = $matrix?->items ?? collect();
        $checks = [
            ['key' => 'iapLineage', 'label' => 'IAP source lineage is preserved', 'met' => $this->lineageValid($engagement, $package)],
            ['key' => 'survey', 'label' => 'Preliminary survey is complete', 'met' => collect(['purpose','background','informationSources','observations','planningImplications'])->every(fn ($key) => filled(data_get($survey, $key)))],
            ['key' => 'objectives', 'label' => 'At least one planning objective exists', 'met' => $objectives->isNotEmpty()],
            ['key' => 'processFlows', 'label' => 'Process flow documentation is complete', 'met' => $flows->isNotEmpty() && $flows->every(fn ($flow) => filled($flow->title) && (filled($flow->description) || filled($flow->document_version_id) || filled($flow->source_reference)))],
            ['key' => 'riskMatrix', 'label' => 'Risk matrix and risk items exist', 'met' => (bool) $matrix && $items->isNotEmpty()],
            ['key' => 'riskObjectives', 'label' => 'Every risk links to an objective in this version', 'met' => $items->every(fn ($item) => $item->objectives()->where('aems_planning_objectives.planning_package_version_id', $version->id)->exists()) && $items->isNotEmpty()],
            ['key' => 'riskProcedures', 'label' => 'Every risk links to an approved-program procedure', 'met' => $items->every(fn ($item) => $item->procedures()->whereIn('audit_program_procedure_id', $procedures->pluck('id'))->exists()) && $items->isNotEmpty()],
            ['key' => 'riskWorkingPapers', 'label' => 'Every risk has a working-paper reference', 'met' => $items->every(fn ($item) => $item->workingPaperLinks()->where(function ($query) use ($engagement) { $query->whereNull('working_paper_id')->orWhereHas('workingPaper', fn ($paper) => $paper->where('audit_engagement_id', $engagement->id)); })->exists()) && $items->isNotEmpty()],
            ['key' => 'approvedAep', 'label' => 'Current AEP is approved', 'met' => $engagement->engagementPlan?->status === 'APPROVED'],
            ['key' => 'approvedProgram', 'label' => 'Current Audit Program is approved', 'met' => (bool) $procedures->first()?->program && in_array($procedures->first()->program->status, ['APPROVED','ACTIVE','COMPLETED'], true)],
        ];
        return ['ready' => collect($checks)->every(fn ($check) => $check['met']), 'checks' => $checks];
    }

    /** @param array<string,mixed> $attributes */
    private function createVersion(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, array $attributes, int $number, ?string $reason): AemsPlanningPackageVersion
    {
        $this->validateReferences($engagement, $attributes);
        $version = AemsPlanningPackageVersion::query()->create(['planning_package_id' => $package->id, 'version_number' => $number, 'preliminary_survey' => $attributes['preliminarySurvey'] ?? [], 'planning_attributes' => $attributes['planningAttributes'] ?? [], 'iap_lineage_snapshot' => $this->lineage($engagement), 'preliminary_survey_document_version_id' => $attributes['preliminarySurveyDocumentVersionId'] ?? data_get($attributes, 'preliminarySurvey.documentVersionId'), 'checksum_sha256' => hash('sha256', json_encode(['preliminarySurvey' => $attributes['preliminarySurvey'] ?? [], 'planningAttributes' => $attributes['planningAttributes'] ?? [], 'objectives' => $attributes['objectives'] ?? [], 'processFlows' => $attributes['processFlows'] ?? [], 'riskMatrix' => $attributes['riskMatrix'] ?? null, 'riskItems' => $attributes['riskItems'] ?? []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 'change_reason' => $reason, 'created_by' => $request->user()->id]);
        foreach (($attributes['objectives'] ?? []) as $index => $objective) AemsPlanningObjective::query()->create(['planning_package_version_id' => $version->id, 'objective_code' => $objective['code'] ?? 'OBJ-'.($index + 1), 'objective_statement' => $objective['statement'] ?? $objective['objectiveStatement'] ?? '', 'source_type' => $objective['sourceType'] ?? 'AEMS', 'source_reference' => $objective['sourceReference'] ?? null, 'sequence' => $objective['sequence'] ?? $index]);
        foreach (($attributes['processFlows'] ?? []) as $index => $flow) AemsProcessFlowDocument::query()->create(['planning_package_version_id' => $version->id, 'flow_code' => $flow['code'] ?? $flow['flowCode'] ?? 'FLOW-'.($index + 1), 'title' => $flow['title'] ?? '', 'description' => $flow['description'] ?? null, 'process_owner_office_id' => $flow['processOwnerOfficeId'] ?? null, 'document_version_id' => $flow['documentVersionId'] ?? null, 'source_type' => $flow['sourceType'] ?? 'AEMS', 'source_reference' => $flow['sourceReference'] ?? null, 'sequence' => $flow['sequence'] ?? $index]);
        $riskMatrix = $attributes['riskMatrix'] ?? null;
        if (is_array($riskMatrix)) {
            $matrix = AemsRiskMatrix::query()->create(['planning_package_version_id' => $version->id, 'matrix_code' => $riskMatrix['code'] ?? $riskMatrix['matrixCode'] ?? 'RM-'.$number, 'title' => $riskMatrix['title'] ?? 'Risk Matrix', 'methodology' => $riskMatrix['methodology'] ?? null, 'risk_appetite' => $riskMatrix['riskAppetite'] ?? null, 'overall_conclusion' => $riskMatrix['overallConclusion'] ?? null]);
            $objectiveIds = $version->objectives()->pluck('id', 'objective_code');
            foreach (($attributes['riskItems'] ?? []) as $index => $risk) {
                $item = AemsRiskMatrixItem::query()->create(['risk_matrix_id' => $matrix->id, 'risk_code' => $risk['riskCode'] ?? 'RISK-'.($index + 1), 'risk_statement' => $risk['riskStatement'] ?? '', 'risk_category' => $risk['riskCategory'] ?? null, 'inherent_likelihood' => $risk['inherentLikelihood'] ?? null, 'inherent_impact' => $risk['inherentImpact'] ?? null, 'inherent_score' => $risk['inherentScore'] ?? null, 'control_description' => $risk['controlDescription'] ?? null, 'control_effectiveness' => $risk['controlEffectiveness'] ?? null, 'residual_likelihood' => $risk['residualLikelihood'] ?? null, 'residual_impact' => $risk['residualImpact'] ?? null, 'residual_score' => $risk['residualScore'] ?? null, 'residual_rating' => $risk['residualRating'] ?? null, 'risk_response' => $risk['riskResponse'] ?? null, 'responsible_office_id' => $risk['responsibleOfficeId'] ?? null, 'sequence' => $risk['sequence'] ?? $index, 'status' => $risk['status'] ?? 'OPEN']);
                foreach (($risk['objectiveCodes'] ?? []) as $code) if ($objectiveIds->get($code)) DB::table('aems_risk_objective_links')->insert(['risk_matrix_item_id' => $item->id, 'planning_objective_id' => $objectiveIds->get($code), 'relationship_basis' => $risk['relationshipBasis'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                foreach (($risk['procedureIds'] ?? []) as $procedureId) DB::table('aems_risk_procedure_links')->insertOrIgnore(['risk_matrix_item_id' => $item->id, 'audit_program_procedure_id' => $procedureId, 'relationship_basis' => $risk['relationshipBasis'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                foreach (($risk['workingPapers'] ?? []) as $paper) AemsRiskWorkingPaperLink::query()->create(['risk_matrix_item_id' => $item->id, 'working_paper_id' => $paper['workingPaperId'] ?? null, 'working_paper_reference' => $paper['reference'] ?? '', 'relationship_basis' => $paper['basis'] ?? null]);
            }
        }
        return $version->fresh(['objectives','processFlows','riskMatrix.items']);
    }

    private function validateReferences(AuditEngagement $engagement, array $attributes): void
    {
        foreach ([$attributes['preliminarySurveyDocumentVersionId'] ?? null, data_get($attributes, 'preliminarySurvey.documentVersionId')] as $id) if ($id && ! DocumentVersion::query()->whereKey($id)->exists()) throw ValidationException::withMessages(['documentVersionId' => ['The selected Core Document Version does not exist.']]);
        foreach (($attributes['processFlows'] ?? []) as $flow) if (! empty($flow['documentVersionId']) && ! DocumentVersion::query()->whereKey($flow['documentVersionId'])->exists()) throw ValidationException::withMessages(['processFlows' => ['Each process-flow document must reference an existing Core Document Version.']]);
        foreach (($attributes['processFlows'] ?? []) as $flow) if (! empty($flow['processOwnerOfficeId']) && ! $engagement->offices()->whereKey($flow['processOwnerOfficeId'])->exists()) throw ValidationException::withMessages(['processFlows' => ['Process-owner offices must be linked to this engagement.']]);
        foreach (($attributes['riskItems'] ?? []) as $risk) if (! empty($risk['responsibleOfficeId']) && ! $engagement->offices()->whereKey($risk['responsibleOfficeId'])->exists()) throw ValidationException::withMessages(['riskItems' => ['Risk responsible offices must be linked to this engagement.']]);
        foreach (($attributes['riskItems'] ?? []) as $risk) foreach (($risk['procedureIds'] ?? []) as $procedureId) if (! AuditProgramProcedure::query()->whereKey($procedureId)->whereHas('program', fn ($q) => $q->where('audit_engagement_id', $engagement->id))->exists()) throw ValidationException::withMessages(['riskItems' => ['Risk relationships may only use procedures from this engagement.']]);
        foreach (($attributes['riskItems'] ?? []) as $risk) foreach (($risk['workingPapers'] ?? []) as $paper) {
            if (blank($paper['reference'] ?? null)) throw ValidationException::withMessages(['riskItems' => ['Every working-paper relationship requires a reference.']]);
            if (! empty($paper['workingPaperId']) && ! WorkingPaper::query()->whereKey($paper['workingPaperId'])->where('audit_engagement_id', $engagement->id)->exists()) throw ValidationException::withMessages(['riskItems' => ['Working-paper relationships may only use papers from this engagement.']]);
        }
    }

    private function ensureReady(AuditEngagement $engagement, AemsPlanningPackage $package, AemsPlanningPackageVersion $version): void { $result = $this->readiness($engagement, $package, $version); if (! $result['ready']) throw ValidationException::withMessages(['readiness' => collect($result['checks'])->where('met', false)->pluck('label')->values()->all()]); }
    private function lockPackage(AuditEngagement $engagement, AemsPlanningPackage $package, int $lockVersion): AemsPlanningPackage { $locked = AemsPlanningPackage::query()->lockForUpdate()->findOrFail($package->id); if ((int) $locked->audit_engagement_id !== (int) $engagement->id) throw ValidationException::withMessages(['package' => ['The planning package does not belong to this engagement.']]); if ($locked->lock_version !== $lockVersion) throw ValidationException::withMessages(['lockVersion' => ['This planning package changed in another session. Refresh before continuing.']]); return $locked; }
    private function nextStatus(string $status, string $action): string
    {
        $next = ['DRAFT' => ['SUBMIT' => 'PENDING_REVIEW'], 'PENDING_REVIEW' => ['REVIEW' => 'PENDING_REVIEW', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED'], 'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED'], 'RESUBMITTED' => ['REVIEW' => 'RESUBMITTED', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED']][$status][$action] ?? null;
        if (! $next) throw ValidationException::withMessages(['action' => ["{$action} is not allowed while the planning package is {$status}."]]);
        return $next;
    }
    private function lineage(AuditEngagement $engagement): array { return ['sourceType' => $engagement->source_type, 'iapPlanEngagementId' => $engagement->iap_plan_engagement_id, 'iapPlanId' => $engagement->iap_plan_id, 'iapPrioritizationItemId' => $engagement->iap_prioritization_item_id, 'iapRiskAssessmentId' => $engagement->iap_risk_assessment_id, 'iapAuditUniverseItemId' => $engagement->iap_audit_universe_item_id, 'sourceSnapshot' => $engagement->source_snapshot, 'capturedAt' => now()->toISOString()]; }
    private function lineageValid(AuditEngagement $engagement, AemsPlanningPackage $package): bool
    {
        if ($package->source_type !== $engagement->source_type) return false;
        if ($engagement->source_type === 'PLANNED' && blank($engagement->source_snapshot)) return false;
        return (int) $package->iap_plan_engagement_id === (int) $engagement->iap_plan_engagement_id
            && (int) $package->iap_plan_id === (int) $engagement->iap_plan_id
            && (int) $package->iap_prioritization_item_id === (int) $engagement->iap_prioritization_item_id
            && (int) $package->iap_risk_assessment_id === (int) $engagement->iap_risk_assessment_id
            && (int) $package->iap_audit_universe_item_id === (int) $engagement->iap_audit_universe_item_id;
    }
    private function emptyReadiness(): array { return ['ready' => false, 'checks' => [['key'=>'package','label'=>'Planning package exists','met'=>false]]]; }
    private function logChange(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, AemsPlanningPackageVersion $version, string $action, $old, $new, ?string $comment): void { $this->support->event($request, $engagement, 'PLANNING_PACKAGE_'.$action, $package->status, 'DRAFT', is_object($old) ? ['versionNumber'=>$old->version_number] : null, ['versionNumber'=>$version->version_number], $comment, 'PLANNING_PACKAGE', $package->id, $version->version_number, $package->package_code); $this->support->audit($request, 'aems.planning-package.'.str($action)->lower(), $engagement, null, ['versionNumber'=>$version->version_number], ['planningPackageId'=>$package->id]); }
    /** @return array<string,mixed> */
    private function packageSnapshot(AemsPlanningPackage $package, ?AemsPlanningPackageVersion $version, $versions = null): array { return ['id'=>$package->id,'packageCode'=>$package->package_code,'status'=>$package->status,'currentVersionNumber'=>$package->current_version_number,'approvedVersionNumber'=>$package->approved_version_number,'lockVersion'=>$package->lock_version,'preparedBy'=>$package->preparer?->only(['id','name','employee_id']),'submittedBy'=>$package->submitter?->only(['id','name','employee_id']),'submittedAt'=>$package->submitted_at?->toISOString(),'approvedBy'=>$package->approver?->only(['id','name','employee_id']),'approvedAt'=>$package->approved_at?->toISOString(),'latestVersion'=>$version ? $this->versionSnapshot($version) : null,'versions'=>collect($versions ?? $package->versions)->map(fn ($entry)=>$this->versionSnapshot($entry))->values(),'reviews'=>$package->reviews()->with('reviewer','version')->get()->map(fn ($review)=>['id'=>$review->id,'versionNumber'=>$review->version?->version_number,'result'=>$review->result,'comment'=>$review->comment,'reviewedAt'=>$review->reviewed_at?->toISOString(),'reviewer'=>$review->reviewer?->only(['id','name','employee_id'])])->values()]; }
    /** @return array<string,mixed> */
    private function versionSnapshot(AemsPlanningPackageVersion $version): array { return ['id'=>$version->id,'versionNumber'=>$version->version_number,'preliminarySurvey'=>$version->preliminary_survey ?? [],'preliminarySurveyDocumentVersionId'=>$version->preliminary_survey_document_version_id,'planningAttributes'=>$version->planning_attributes ?? [],'iapLineageSnapshot'=>$version->iap_lineage_snapshot ?? [],'checksumSha256'=>$version->checksum_sha256,'changeReason'=>$version->change_reason,'createdBy'=>$version->creator?->only(['id','name','employee_id']),'createdAt'=>$version->created_at?->toISOString(),'objectives'=>$version->objectives->map(fn ($o)=>['id'=>$o->id,'code'=>$o->objective_code,'statement'=>$o->objective_statement,'sourceType'=>$o->source_type,'sourceReference'=>$o->source_reference,'sequence'=>$o->sequence])->values(),'processFlows'=>$version->processFlows->map(fn ($f)=>['id'=>$f->id,'code'=>$f->flow_code,'title'=>$f->title,'description'=>$f->description,'processOwnerOfficeId'=>$f->process_owner_office_id,'documentVersionId'=>$f->document_version_id,'sourceType'=>$f->source_type,'sourceReference'=>$f->source_reference,'sequence'=>$f->sequence])->values(),'riskMatrix'=>$version->riskMatrix ? ['id'=>$version->riskMatrix->id,'code'=>$version->riskMatrix->matrix_code,'title'=>$version->riskMatrix->title,'methodology'=>$version->riskMatrix->methodology,'riskAppetite'=>$version->riskMatrix->risk_appetite,'overallConclusion'=>$version->riskMatrix->overall_conclusion,'items'=>$version->riskMatrix->items->map(fn ($item)=>['id'=>$item->id,'riskCode'=>$item->risk_code,'riskStatement'=>$item->risk_statement,'riskCategory'=>$item->risk_category,'inherentLikelihood'=>$item->inherent_likelihood,'inherentImpact'=>$item->inherent_impact,'inherentScore'=>$item->inherent_score,'controlDescription'=>$item->control_description,'controlEffectiveness'=>$item->control_effectiveness,'residualLikelihood'=>$item->residual_likelihood,'residualImpact'=>$item->residual_impact,'residualScore'=>$item->residual_score,'residualRating'=>$item->residual_rating,'riskResponse'=>$item->risk_response,'responsibleOfficeId'=>$item->responsible_office_id,'sequence'=>$item->sequence,'status'=>$item->status,'objectives'=>$item->objectives->map(fn ($objective)=>['code'=>$objective->objective_code,'statement'=>$objective->objective_statement])->values(),'procedures'=>$item->procedures->map(fn ($procedure)=>['id'=>$procedure->id,'code'=>$procedure->procedure_code,'objective'=>$procedure->objective])->values(),'workingPapers'=>$item->workingPaperLinks->map(fn ($link)=>['workingPaperId'=>$link->working_paper_id,'reference'=>$link->working_paper_reference,'basis'=>$link->relationship_basis])->values()])->values()] : null]; }
}
