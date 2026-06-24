<?php

namespace ApiPerformanceAnalyzer\Reporting;

use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Support\Carbon;

/**
 * Assembles the data for the one-document performance digest. The DB stays the
 * source of truth — this is an export layer. The report is a single document with
 * problems and their recommendations kept ADJACENT (not split across files), so
 * the reader never cross-references.
 */
class ReportBuilder
{
    public function __construct(protected MetricsRepository $metrics) {}

    public function build(Carbon $from, Carbon $to): array
    {
        $filters = ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()];

        $overview = $this->metrics->overview($filters);
        $slowest = $this->metrics->endpoints($filters, 'p95', 10);
        $worstErrors = $this->metrics->endpoints($filters, 'error_rate', 10)
            ->filter(fn ($e) => $e['error_rate'] > 0)->values();
        $nPlusOne = $this->metrics->nPlusOneSuspects($filters, 10);
        $slowQueries = $this->metrics->slowQueries($filters, 10);

        // Problems paired one-to-one with their advisory recommendation.
        $problems = $slowest->map(function ($e) {
            $recs = [];
            if ($e['p95_ms'] >= config('apa.thresholds.slow_request_ms', 500)) {
                $recs[] = "p95 is {$e['p95_ms']}ms — profile the endpoint; cache or paginate heavy responses.";
            }
            if ($e['n_plus_one_count'] > 0) {
                $recs[] = "{$e['n_plus_one_count']} N+1 occurrence(s) — eager-load the repeated relation.";
            }
            if ($e['avg_queries'] > 20) {
                $recs[] = "Averages {$e['avg_queries']} queries/request — reduce query fan-out.";
            }
            if ($e['error_rate'] > 0.02) {
                $recs[] = 'Error rate '.round($e['error_rate'] * 100, 1).'% — investigate 5xx responses.';
            }

            return ['endpoint' => $e, 'recommendations' => $recs ?: ['Within thresholds — monitor.']];
        });

        return [
            'window' => ['from' => $from, 'to' => $to],
            'overview' => $overview,
            'problems' => $problems,
            'worst_errors' => $worstErrors,
            'n_plus_one' => $nPlusOne,
            'slow_queries' => $slowQueries,
            'generated_at' => now(),
        ];
    }
}
