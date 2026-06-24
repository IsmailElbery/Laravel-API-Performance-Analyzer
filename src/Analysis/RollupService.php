<?php

namespace ApiPerformanceAnalyzer\Analysis;

use ApiPerformanceAnalyzer\Models\DailyEndpointStat;
use ApiPerformanceAnalyzer\Models\RequestProfile;
use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;

/**
 * Aggregates raw profiles for a given day into apa_daily_endpoint_stats, attaching
 * a health score per endpoint. Long-term reporting then reads these small daily
 * rows instead of the raw (pruned) profile table.
 */
class RollupService
{
    public function __construct(
        protected Config $config,
        protected MetricsRepository $metrics,
        protected HealthScorer $scorer,
    ) {}

    /** Roll up a single day (defaults to yesterday). Returns rows written. */
    public function rollup(?Carbon $date = null): int
    {
        $date = ($date ?? now()->subDay())->startOfDay();
        $from = $date->copy();
        $to = $date->copy()->endOfDay();

        $filters = ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()];

        // One aggregate row per (uri, method); reuse the repository so percentile
        // math stays in one place.
        $endpoints = $this->metrics->endpoints($filters, 'count', 10000);

        $written = 0;
        foreach ($endpoints as $e) {
            $count = (int) $e['count'];
            $nPlusOneRate = $count > 0 ? $e['n_plus_one_count'] / $count : 0.0;

            $health = $this->scorer->score([
                'count' => $count,
                'p95_ms' => $e['p95_ms'],
                'error_rate' => $e['error_rate'],
                'avg_queries' => $e['avg_queries'],
                'n_plus_one_rate' => $nPlusOneRate,
            ]);

            DailyEndpointStat::query()->updateOrCreate(
                ['date' => $date->toDateString(), 'uri' => $e['uri'], 'method' => $e['method']],
                [
                    'count' => $count,
                    'avg_ms' => $e['avg_ms'],
                    'p95_ms' => $e['p95_ms'],
                    'error_rate' => $e['error_rate'],
                    'avg_queries' => $e['avg_queries'],
                    'n_plus_one_count' => $e['n_plus_one_count'],
                    'health_score' => $health['score'],
                    'health_grade' => $health['grade'],
                ]
            );
            $written++;
        }

        return $written;
    }
}
