<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\Branch;
use App\Services\ReportService;
use App\Services\SimplePdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private SimplePdf $pdf) {}

    public function show(ReportRequest $request, Branch $branch): JsonResponse
    {
        return response()->json(['data' => $this->report($request, $branch)]);
    }

    public function csv(ReportRequest $request, Branch $branch): Response
    {
        $report = $this->report($request, $branch);
        $rows = [['QueueCare operational report', $report['branch']['name']], ['Date range', $report['range']['from'].' to '.$report['range']['to']], [], ['Metric', 'Value']];
        foreach ($report['summary'] as $metric => $value) {
            $rows[] = [str_replace('_', ' ', ucfirst($metric)), $value];
        }
        $rows[] = [];
        $rows[] = ['Service', 'Tickets', 'Served', 'Average wait (min)', 'Average service (min)'];
        foreach ($report['services'] as $service) {
            $rows[] = [$service['service'], $service['tickets'], $service['served'], $service['average_wait_minutes'], $service['average_service_minutes']];
        }
        $stream = fopen('php://temp', 'w+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '\\');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="queuecare-report-'.$branch->slug.'.csv"']);
    }

    public function pdf(ReportRequest $request, Branch $branch): Response
    {
        $report = $this->report($request, $branch);
        $summary = $report['summary'];
        $lines = [
            $report['branch']['name'].' | '.$report['range']['from'].' to '.$report['range']['to'], '',
            'Total tickets: '.$summary['total_tickets'], 'Served: '.$summary['served'],
            'Cancelled: '.$summary['cancelled'].' ('.$summary['cancellation_rate'].'%)',
            'Skipped: '.$summary['skipped'].' ('.$summary['skip_rate'].'%)',
            'Average wait: '.$summary['average_wait_minutes'].' minutes',
            'Average service: '.$summary['average_service_minutes'].' minutes', '', 'Service performance',
        ];
        foreach ($report['services'] as $service) {
            $lines[] = sprintf('%s: %d tickets, %.1f min wait, %.1f min service', $service['service'], $service['tickets'], $service['average_wait_minutes'], $service['average_service_minutes']);
        }

        return response($this->pdf->make('QueueCare operational report', $lines), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="queuecare-report-'.$branch->slug.'.pdf"']);
    }

    /** @return array<string, mixed> */
    private function report(ReportRequest $request, Branch $branch): array
    {
        $validated = $request->validated();
        $to = CarbonImmutable::parse($validated['to'] ?? 'today', $branch->timezone);
        $from = CarbonImmutable::parse($validated['from'] ?? $to->subDays(29)->toDateString(), $branch->timezone);
        abort_if($from->diffInDays($to) > 365, 422, 'The report range may not exceed 366 days.');

        return $this->reports->build($branch, $from, $to, $request->user()->isSuperAdmin());
    }
}
