<?php

namespace ApiPerformanceAnalyzer\Analysis;

use ApiPerformanceAnalyzer\Models\IndexRecommendation;
use ApiPerformanceAnalyzer\Models\Query;
use Illuminate\Support\Carbon;

/**
 * Surfaces frequent slow queries (grouped by sql_hash) as ADVISORY index
 * recommendations: sample SQL, frequency, avg time, triggering endpoints.
 *
 * EXPLAIN-based analysis is intentionally descoped from production — replaying
 * statements adds load and bindings may be stale. EXPLAIN is gated to the
 * opt-in `apa:explain` command for dev/staging only. Recommendations here are
 * heuristic and never auto-applied.
 */
class IndexRecommender
{
    /** Rebuild recommendations from the last N days of slow queries. */
    public function rebuild(int $days = 7, int $minFrequency = 5, int $limit = 100): int
    {
        $since = now()->subDays($days);

        $groups = Query::query()
            ->where('is_slow', true)
            ->whereHas('profile', fn ($p) => $p->where('created_at', '>=', $since))
            ->selectRaw('sql_hash')
            ->selectRaw('count(*) as frequency')
            ->selectRaw('avg(time_ms) as avg_time_ms')
            ->selectRaw('min(sql) as sample_sql')
            ->groupBy('sql_hash')
            ->having('frequency', '>=', $minFrequency)
            ->orderByDesc('avg_time_ms')
            ->limit($limit)
            ->get();

        $written = 0;
        foreach ($groups as $g) {
            $endpoints = Query::query()
                ->where('sql_hash', $g->sql_hash)
                ->whereHas('profile', fn ($p) => $p->where('created_at', '>=', $since))
                ->with('profile:id,uri,method')
                ->get()
                ->map(fn ($q) => $q->profile?->uri)
                ->filter()
                ->unique()
                ->values()
                ->all();

            IndexRecommendation::query()->updateOrCreate(
                ['sql_hash' => $g->sql_hash],
                [
                    'sample_sql' => $g->sample_sql,
                    'frequency' => (int) $g->frequency,
                    'avg_time_ms' => round((float) $g->avg_time_ms, 2),
                    'endpoints' => $endpoints,
                    'recommendation' => $this->heuristic($g->sample_sql),
                    'created_at' => now(),
                ]
            );
            $written++;
        }

        return $written;
    }

    /** Cheap heuristic: point at WHERE/JOIN columns as candidate index targets. */
    protected function heuristic(string $sql): string
    {
        $columns = [];

        if (preg_match_all('/\bwhere\b(.+?)(\border by\b|\bgroup by\b|\blimit\b|$)/is', $sql, $m)) {
            if (preg_match_all('/`?(\w+)`?\s*(=|<|>|in|like)/i', $m[1][0] ?? '', $cols)) {
                $columns = array_slice(array_unique($cols[1]), 0, 4);
            }
        }

        if ($columns === []) {
            return 'Advisory: review this frequent slow query for a missing index. Run `apa:explain '
                .substr(sha1($sql), 0, 8).'…` in staging for a query plan.';
        }

        return 'Advisory: consider an index on '.implode(', ', $columns)
            .'. Verify with `apa:explain` in staging before applying — never auto-applied.';
    }
}
