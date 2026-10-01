<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DateRangeRequest;
use App\Services\ReportService;
use App\Support\Grid\CsvExport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(DateRangeRequest $request, string $type, ReportService $reports): JsonResponse
    {
        abort_unless(array_key_exists($type, ReportService::TYPES), 404, 'Unknown report.');

        return response()->json($reports->run($type, $request->from(), $request->to()));
    }

    public function export(DateRangeRequest $request, string $type, ReportService $reports): StreamedResponse
    {
        abort_unless(array_key_exists($type, ReportService::TYPES), 404, 'Unknown report.');

        $report = $reports->run($type, $request->from(), $request->to());

        /** @var list<array{field: string, header: string}> $columns */
        $columns = $report['columns'];
        /** @var list<array<string, mixed>> $rows */
        $rows = $report['rows'];

        $map = [];
        foreach ($columns as $column) {
            $map[$column['header']] = $column['field'];
        }

        return CsvExport::download(
            "{$type}-{$report['from']}-{$report['to']}.csv",
            $rows,
            $map,
        );
    }
}
