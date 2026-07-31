<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AemsIssueRequest;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditIssue;
use App\Services\AemsFindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Exposes supported issue capture, review, dismissal, and conversion. */
class AemsIssueController extends Controller
{
    public function __construct(private readonly AemsFindingService $findings) {}

    public function store(AemsIssueRequest $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('create', [AuditIssue::class, $engagement]);
        $issue = $this->findings->createIssue($request, $engagement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Draft audit issue created.',
            'data' => ['issue' => $this->findings->issueData($issue)],
        ], 201);
    }

    public function update(
        AemsIssueRequest $request,
        AuditEngagement $engagement,
        AuditIssue $issue,
    ): JsonResponse {
        Gate::authorize('prepare', $issue);
        $issue = $this->findings->updateIssue(
            $request,
            $engagement,
            $issue,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft issue updated.',
            'data' => ['issue' => $this->findings->issueData($issue)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditIssue $issue,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'VALIDATE', 'DISMISS', 'CONVERT'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        Gate::authorize(match ($validated['action']) {
            'SUBMIT' => 'prepare',
            'VALIDATE' => 'validate',
            'DISMISS' => 'dismiss',
            'CONVERT' => 'convert',
        }, $issue);
        $result = $this->findings->transitionIssue(
            $request,
            $engagement,
            $issue,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Issue workflow action completed.',
            'data' => $result instanceof AuditFinding
                ? ['finding' => $this->findings->findingData($result)]
                : ['issue' => $this->findings->issueData($result)],
        ]);
    }
}
