<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AemsPlanningPackage;
use App\Models\AuditEngagement;
use App\Services\AemsAccessService;
use App\Services\AemsPlanningPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AemsPlanningPackageController extends Controller
{
    public function __construct(private readonly AemsPlanningPackageService $packages, private readonly AemsAccessService $access) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.view');
        return response()->json(['success' => true, 'data' => $this->packages->workspace($engagement)]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $package = $this->packages->create($request, $engagement, $this->content($request));
        return response()->json(['success' => true, 'message' => 'Draft planning package created.', 'data' => ['package' => $package]], 201);
    }

    public function update(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package): JsonResponse
    {
        $content = $this->content($request);
        $content['lockVersion'] = $request->validate(['lockVersion' => ['required','integer','min:1']])['lockVersion'];
        $package = $this->packages->update($request, $engagement, $package, $content);
        return response()->json(['success' => true, 'message' => 'A new immutable planning package version was created.', 'data' => ['package' => $package]]);
    }

    public function transition(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', Rule::in(['SUBMIT','REVIEW','RETURN','RESUBMIT','APPROVE'])], 'lockVersion' => ['required','integer','min:1'], 'comment' => ['nullable','string','max:4000']]);
        $package = $this->packages->transition($request, $engagement, $package, $validated['action'], $validated['lockVersion'], $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Planning package workflow action completed.', 'data' => ['package' => $package]]);
    }

    public function revise(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package): JsonResponse
    {
        $validated = $request->validate(['lockVersion' => ['required','integer','min:1'], 'reason' => ['required','string','min:5','max:4000']]);
        $package = $this->packages->revise($request, $engagement, $package, $validated['lockVersion'], $validated['reason']);
        return response()->json(['success' => true, 'message' => 'Formal planning package revision started.', 'data' => ['package' => $package]]);
    }

    /** @return array<string,mixed> */
    private function content(Request $request): array
    {
        return $request->validate([
            'preliminarySurvey' => ['nullable','array'],
            'preliminarySurvey.purpose' => ['nullable','string','max:10000'],
            'preliminarySurvey.background' => ['nullable','string','max:10000'],
            'preliminarySurvey.informationSources' => ['nullable','string','max:10000'],
            'preliminarySurvey.interviews' => ['nullable','string','max:10000'],
            'preliminarySurvey.walkthroughs' => ['nullable','string','max:10000'],
            'preliminarySurvey.observations' => ['nullable','string','max:10000'],
            'preliminarySurvey.planningImplications' => ['nullable','string','max:10000'],
            'preliminarySurvey.documentVersionId' => ['nullable','integer','exists:document_versions,id'],
            'preliminarySurveyDocumentVersionId' => ['nullable','integer','exists:document_versions,id'],
            'planningAttributes' => ['nullable','array'],
            'objectives' => ['nullable','array'], 'objectives.*' => ['array'],
            'objectives.*.code' => ['required_with:objectives.*','string','max:80'], 'objectives.*.statement' => ['required_with:objectives.*','string','max:10000'],
            'processFlows' => ['nullable','array'], 'processFlows.*' => ['array'],
            'processFlows.*.code' => ['required_with:processFlows.*','string','max:80'], 'processFlows.*.title' => ['required_with:processFlows.*','string','max:255'], 'processFlows.*.description' => ['nullable','string','max:10000'], 'processFlows.*.documentVersionId' => ['nullable','integer','exists:document_versions,id'], 'processFlows.*.processOwnerOfficeId' => ['nullable','integer','exists:offices,id'], 'processFlows.*.auditAreaId' => ['nullable','integer','exists:audit_areas,id'], 'processFlows.*.auditFocusId' => ['nullable','integer','exists:audit_focuses,id'], 'processFlows.*.scopeStatement' => ['nullable','string','max:10000'], 'processFlows.*.steps' => ['nullable','array'], 'processFlows.*.inputs' => ['nullable','array'], 'processFlows.*.outputs' => ['nullable','array'], 'processFlows.*.recordsSystems' => ['nullable','array'], 'processFlows.*.controls' => ['nullable','array'], 'processFlows.*.decisionPoints' => ['nullable','array'], 'processFlows.*.riskPoints' => ['nullable','array'], 'processFlows.*.limitations' => ['nullable','string','max:10000'],
            'kpis' => ['nullable','array'], 'kpis.*' => ['array'], 'kpis.*.code' => ['nullable','string','max:80'], 'kpis.*.name' => ['required_with:kpis.*','string','max:255'], 'kpis.*.target' => ['required_with:kpis.*','string','max:255'], 'kpis.*.measurementMethod' => ['required_with:kpis.*','string','max:10000'], 'kpis.*.responsibleOfficeId' => ['nullable','integer','exists:offices,id'],
            'riskMatrix' => ['nullable','array'], 'riskMatrix.code' => ['nullable','string','max:80'], 'riskMatrix.title' => ['nullable','string','max:255'], 'riskMatrix.methodology' => ['nullable','string','max:10000'],
            'riskMatrices' => ['nullable','array'], 'riskMatrices.*' => ['array'], 'riskMatrices.*.code' => ['required_with:riskMatrices.*','string','max:80'], 'riskMatrices.*.title' => ['required_with:riskMatrices.*','string','max:255'], 'riskMatrices.*.auditAreaId' => ['nullable','integer','exists:audit_areas,id'], 'riskMatrices.*.auditFocusId' => ['nullable','integer','exists:audit_focuses,id'], 'riskMatrices.*.riskItems' => ['nullable','array'],
            'riskItems' => ['nullable','array'], 'riskItems.*' => ['array'], 'riskItems.*.riskCode' => ['required_with:riskItems.*','string','max:80'], 'riskItems.*.riskStatement' => ['required_with:riskItems.*','string','max:10000'], 'riskItems.*.objectiveCodes' => ['nullable','array'], 'riskItems.*.procedureIds' => ['nullable','array'], 'riskItems.*.workingPapers' => ['nullable','array'], 'riskItems.*.auditAreaId' => ['nullable','integer','exists:audit_areas,id'], 'riskItems.*.auditFocusId' => ['nullable','integer','exists:audit_focuses,id'], 'riskItems.*.processFlowId' => ['nullable','integer','exists:aems_process_flow_documents,id'], 'riskItems.*.processName' => ['nullable','string','max:255'], 'riskItems.*.riskArea' => ['nullable','string','max:255'], 'riskItems.*.plannedAuditApproach' => ['nullable','string','max:10000'], 'riskItems.*.criteria' => ['nullable','string','max:10000'],
            'plannedWorkingPapers' => ['nullable','array'], 'plannedWorkingPapers.*' => ['array'], 'plannedWorkingPapers.*.procedureId' => ['nullable','integer','exists:audit_program_procedures,id'], 'plannedWorkingPapers.*.riskItemId' => ['nullable','integer','exists:aems_risk_matrix_items,id'], 'plannedWorkingPapers.*.reference' => ['required_with:plannedWorkingPapers.*','string','max:120'], 'plannedWorkingPapers.*.title' => ['required_with:plannedWorkingPapers.*','string','max:255'], 'plannedWorkingPapers.*.requiredEvidence' => ['required_with:plannedWorkingPapers.*','string','max:10000'],
            'changeReason' => ['nullable','string','max:4000'],
        ]);
    }
}
