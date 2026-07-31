<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditLog;
use App\Services\AemsDashboardService;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Provides the access-scoped AEMS portfolio tracker and operational dashboard. */
class AemsDashboardController extends Controller
{
    public function __construct(private readonly AemsDashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditEngagement::class);
        $validated = $request->validate($this->filterRules(true));

        return response()->json([
            'success' => true,
            'data' => $this->dashboard->dashboard($request->user(), $validated),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AuditEngagement::class);
        abort_unless($request->user()->hasPermission('aems.engagement.export'), 403);
        $filters = $request->validate($this->filterRules(false));
        $report = $this->dashboard->portfolioReport($request->user(), $filters);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'aems.dashboard.exported',
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => [
                'module' => 'AEMS',
                'report' => 'engagement-progress',
                'format' => 'csv',
                'filters' => $filters,
                'row_count' => count($report['rows']),
                'file_name' => $report['fileName'].'.csv',
            ],
        ]);
        ActivityRecorder::record(
            $request,
            'aems.dashboard.exported',
            'Exported the AEMS Engagement Progress Report as CSV.',
            metadata: [
                'module' => 'AEMS',
                'report' => 'engagement-progress',
                'format' => 'csv',
                'filters' => $filters,
                'rowCount' => count($report['rows']),
            ],
        );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [$report['title']]);
            fputcsv($handle, [$report['description']]);
            fputcsv($handle, ['Generated at', $report['generatedAt']]);
            fputcsv($handle, []);
            fputcsv($handle, array_column($report['columns'], 'label'));
            foreach ($report['rows'] as $row) {
                fputcsv(
                    $handle,
                    collect($report['columns'])
                        ->map(fn (array $column) => $row[$column['key']] ?? '')
                        ->all(),
                );
            }
            fclose($handle);
        }, $report['fileName'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, array<int, string>> */
    private function filterRules(bool $includePagination): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', AuditEngagement::STATUSES)],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'sortBy' => [
                'nullable',
                'in:updated_at,engagement_code,title,planned_end_date,expected_report_date,status',
            ],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ];
        if ($includePagination) {
            $rules['page'] = ['nullable', 'integer', 'min:1'];
            $rules['perPage'] = ['nullable', 'integer', 'min:5', 'max:50'];
        }

        return $rules;
    }
}
