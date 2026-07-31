<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CmsRecommendationIndexRequest;
use App\Http\Resources\CmsRecommendationDetailResource;
use App\Http\Resources\CmsRecommendationResource;
use App\Models\CmsRecommendationCase;
use App\Services\Cms\CmsActionPlanService;
use App\Services\Cms\CmsRecommendationRegistryService;
use App\Services\Cms\CmsRecommendationScopeService;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Read-only CMS recommendation registry backed by AEMS-created cases. */
class CmsRecommendationController extends Controller
{
    public function __construct(
        private readonly CmsRecommendationRegistryService $registry,
        private readonly CmsRecommendationScopeService $scope,
        private readonly CmsActionPlanService $actionPlans,
    ) {}

    public function index(CmsRecommendationIndexRequest $request): JsonResponse
    {
        $cases = $this->registry->paginate($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'recommendations' => CmsRecommendationResource::collection(
                    $cases->getCollection(),
                ),
                'filters' => $this->registry->filterOptions($request->user()),
                'pagination' => [
                    'currentPage' => $cases->currentPage(),
                    'lastPage' => $cases->lastPage(),
                    'perPage' => $cases->perPage(),
                    'total' => $cases->total(),
                    'from' => $cases->firstItem(),
                    'to' => $cases->lastItem(),
                ],
                'evaluationDate' => now()->toDateString(),
            ],
        ]);
    }

    public function show(Request $request, int $recommendation): JsonResponse
    {
        $case = $this->scope->resolveVisibleCase($request->user(), $recommendation);
        $case->load($this->detailRelations());
        if ($case->actionPlan?->currentVersion) {
            $case->actionPlan->currentVersion->setAttribute(
                'available_actions',
                $this->actionPlans->permittedActions(
                    $request->user(),
                    $case->actionPlan,
                    $case->actionPlan->currentVersion,
                ),
            );
        }
        $this->recordView($request, $case);

        return response()->json([
            'success' => true,
            'data' => [
                'recommendation' => new CmsRecommendationDetailResource($case),
            ],
        ]);
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'recommendation.transferActor',
            'leadResponsibleOffice',
            'currentAssignment.user',
            'currentAssignment.assigner',
            'assignments.user',
            'assignments.assigner',
            'assignments.ender',
            'events.actor',
            'actionPlan.currentVersion.milestones',
            'actionPlan.acceptedVersion',
        ];
    }

    private function recordView(Request $request, CmsRecommendationCase $case): void
    {
        $recorded = Cache::add(
            "agis:record-view:{$request->user()->id}:CMS_RECOMMENDATION:{$case->id}",
            true,
            now()->addMinutes(5),
        );
        if (! $recorded) {
            return;
        }

        ActivityRecorder::record(
            $request,
            'cms.recommendation.viewed',
            'Viewed an authorized CMS recommendation.',
            metadata: [
                'module' => 'CMS',
                'recordType' => 'CMS_RECOMMENDATION',
                'recordId' => $case->id,
                'recordCode' => sprintf('CMS-REC-%06d', $case->id),
            ],
        );
    }
}
