<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Services\AemsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exposes immutable Draft and Final Audit Report generation and issuance. */
class AemsReportController extends Controller
{
    public function __construct(private readonly AemsReportService $reports) {}

    public function engagements(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyPermission(['aems.report.view', 'aems.report.view_issued']),
            403,
            'You cannot view AEMS reports.',
        );

        return response()->json([
            'success' => true,
            'data' => ['engagements' => $this->reports->engagements($request)],
        ]);
    }

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyPermission(['aems.report.view', 'aems.report.view_issued']),
            403,
            'You cannot view AEMS reports.',
        );

        return response()->json([
            'success' => true,
            'data' => $this->reports->workspace($request, $engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $report = $this->reports->createDraft(
            $request,
            $engagement,
            $this->content($request, false, false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft Report generated.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ], 201);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $report = $this->reports->revise(
            $request,
            $engagement,
            $report,
            $this->content($request, $report->report_stage === 'FINAL_REPORT', true),
        );

        return response()->json([
            'success' => true,
            'message' => 'Immutable report revision generated.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ]);
    }

    public function createFinal(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $report = $this->reports->createFinal(
            $request,
            $engagement,
            $report,
            $this->content($request, true, true),
        );

        return response()->json([
            'success' => true,
            'message' => 'Final Report draft generated from finalized Findings.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'RETURN', 'APPROVE', 'ISSUE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:20000'],
            'issuanceDate' => ['nullable', 'date'],
        ]);
        $report = $this->reports->transition(
            $request,
            $engagement,
            $report,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
            $validated['issuanceDate'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Report workflow action completed.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ]);
    }

    public function transferRecommendations(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $transfers = $this->reports->retryCmsTransfer(
            $request,
            $engagement,
            $report,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Recommendation transfer is synchronized idempotently.',
            'data' => ['transfers' => $transfers],
        ]);
    }

    public function download(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): StreamedResponse {
        $documentVersion = $this->reports->download(
            $request,
            $engagement,
            $report,
            $version,
        );

        return Storage::disk('local')->download(
            $documentVersion->storage_path,
            $documentVersion->original_file_name,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @return array<string, mixed> */
    private function content(Request $request, bool $final, bool $revision): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'executiveSummary' => ['required', 'string', 'min:10', 'max:60000'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.content' => ['required', 'string', 'max:60000'],
            'findingIds' => ['required', 'array', 'min:1'],
            'findingIds.*' => ['required', 'integer', 'distinct'],
            'confidentialityLevelId' => ['required', 'integer'],
            'approvingAuthority' => [
                $final ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'recipients' => [$final ? 'required' : 'nullable', 'array', $final ? 'min:1' : 'max:100'],
            'recipients.*.recipientType' => [
                'required',
                Rule::in(['USER', 'OFFICE', 'EXTERNAL']),
            ],
            'recipients.*.userId' => ['nullable', 'integer'],
            'recipients.*.officeId' => ['nullable', 'integer'],
            'recipients.*.externalName' => ['nullable', 'string', 'max:255'],
            'recipients.*.externalEmail' => ['nullable', 'email', 'max:255'],
            'recipients.*.deliveryMethod' => [
                'nullable',
                Rule::in(['SYSTEM', 'EMAIL', 'HAND_DELIVERY', 'REGISTERED_MAIL']),
            ],
            'changeReason' => [
                $revision ? 'required' : 'nullable',
                'string',
                'max:10000',
            ],
            'lockVersion' => [$revision ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
