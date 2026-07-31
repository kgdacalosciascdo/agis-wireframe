<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditRecommendation;
use App\Models\EngagementClosure;
use App\Models\EngagementRetentionRecord;
use App\Services\AemsClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AemsClosureController extends Controller
{
    public function __construct(private readonly AemsClosureService $closures) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->closures->workspace($request, $engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $validated = $request->validate($this->closureRules(false));
        $this->closures->create($request, $engagement, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Formal Engagement Closure created.',
            'data' => $this->closures->workspace($request, $engagement),
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
    ): JsonResponse {
        $validated = $request->validate($this->closureRules(true));
        $this->closures->update($request, $engagement, $closure, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Engagement Closure updated.',
            'data' => $this->closures->workspace($request, $engagement),
        ]);
    }

    public function refreshChecklist(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
    ): JsonResponse {
        $this->closures->refreshChecklist($request, $engagement, $closure);

        return response()->json([
            'success' => true,
            'message' => 'Closure checklist re-evaluated from authoritative records.',
            'data' => $this->closures->workspace($request, $engagement),
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        string $action,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'engagementLockVersion' => ['nullable', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $this->closures->transition(
            $request,
            $engagement,
            $closure,
            $action,
            $validated['lockVersion'],
            $validated['engagementLockVersion'] ?? null,
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Formal Closure workflow action completed.',
            'data' => $this->closures->workspace($request, $engagement->fresh()),
        ]);
    }

    public function saveRetention(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'retentionClassificationCode' => ['required', 'string', 'max:80'],
            'retentionTriggerCode' => ['required', 'string', 'max:80'],
            'retentionStartDate' => ['required', 'date'],
            'retentionPeriodValue' => ['nullable', 'integer', 'min:1'],
            'retentionPeriodUnit' => ['nullable', Rule::in(['DAYS', 'MONTHS', 'YEARS'])],
            'permanentFlag' => ['sometimes', 'boolean'],
            'scheduledDispositionDate' => ['nullable', 'date'],
            'custodianUserId' => ['required', 'exists:users,id'],
            'custodianOfficeId' => ['required', 'exists:offices,id'],
            'storageLocationDescription' => ['nullable', 'string', 'max:10000'],
            'legalHoldFlag' => ['sometimes', 'boolean'],
            'legalHoldReference' => ['nullable', 'string', 'max:255', 'required_if:legalHoldFlag,true'],
        ]);
        $record = $this->closures->saveRetention($request, $engagement, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Retention and custody metadata saved.',
            'data' => ['retention' => $record],
        ]);
    }

    public function approveRetention(
        Request $request,
        AuditEngagement $engagement,
        EngagementRetentionRecord $retention,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $record = $this->closures->approveRetention(
            $request,
            $engagement,
            $retention,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Retention and custody metadata approved.',
            'data' => ['retention' => $record],
        ]);
    }

    public function addLesson(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'categoryCode' => ['required', Rule::in([
                'PLANNING', 'RESOURCE', 'METHODOLOGY', 'DATA_ACCESS',
                'AUDITEE_COORDINATION', 'SUPERVISION', 'DOCUMENTATION',
                'REPORTING', 'SYSTEM', 'TRAINING', 'OTHER',
            ])],
            'observation' => ['required', 'string', 'max:20000'],
            'impact' => ['required', 'string', 'max:20000'],
            'recommendedImprovement' => ['required', 'string', 'max:20000'],
            'responsibleOfficeId' => ['nullable', 'exists:offices,id'],
            'responsibleUserId' => ['nullable', 'exists:users,id'],
            'targetDate' => ['nullable', 'date'],
            'confidentialityCode' => ['required', 'string', 'max:60'],
        ]);
        $lesson = $this->closures->addLesson($request, $engagement, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Lesson learned recorded without altering issued results.',
            'data' => ['lesson' => $lesson],
        ], 201);
    }

    public function excludeRecommendation(
        Request $request,
        AuditEngagement $engagement,
        AuditRecommendation $recommendation,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:10000'],
            'authority' => ['required', 'string', 'max:255'],
        ]);
        $recommendation = $this->closures->excludeRecommendation(
            $request,
            $engagement,
            $recommendation,
            $validated['reason'],
            $validated['authority'],
        );

        return response()->json([
            'success' => true,
            'message' => 'CMS exclusion formally authorized.',
            'data' => ['recommendation' => $recommendation],
        ]);
    }

    /** @return array<string, mixed> */
    private function closureRules(bool $update): array
    {
        return [
            'lockVersion' => [$update ? 'required' : 'nullable', 'integer', 'min:1'],
            'completionAssessmentId' => ['nullable', 'exists:completion_assessments,id'],
            'closureSummary' => ['required', 'string', 'max:20000'],
            'unresolvedMattersSummary' => ['nullable', 'string', 'max:20000'],
            'lessonsLearnedSummary' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
