<?php

namespace ApiPerformanceAnalyzer\Repositories;

use ApiPerformanceAnalyzer\Models\Query;
use ApiPerformanceAnalyzer\Models\RequestProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single query surface shared by the dashboard and the REST API. Percentiles
 * are computed with a portable OFFSET trick (works identically on MySQL and
 * PostgreSQL) rather than vendor-specific percentile functions: order by the
 * metric, jump to the ceil(p*(n-1)) row. Exact, and cheap when paginated.
 */
class MetricsRepository
{
    /**
     * Paginated, filterable request list.
     *
     * @param array{method?:string,status?:int,slow?:bool,n_plus_one?:bool,uri?:string,from?:string,to?:string,per_page?:int} $filters
     */
    public function requests(array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->perPage($filters['per_page'] ?? null);

        return $this->filtered($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?RequestProfile
    {
        return RequestProfile::query()
            ->with(['queries', 'httpCalls'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Per-endpoint aggregates (count, avg, p95, error rate, avg queries, N+1).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function endpoints(array $filters = [], string $sort = 'p95', int $limit = 50): Collection
    {
        $base = $this->filtered($filters);

        $rows = (clone $base)
            ->selectRaw('uri, method')
            ->selectRaw('count(*) as count')
            ->selectRaw('avg(duration_ms) as avg_ms')
            ->selectRaw('avg(db_query_count) as avg_queries')
            ->selectRaw('sum(case when status_code >= 500 then 1 else 0 end) as errors')
            ->selectRaw('sum(case when has_n_plus_one then 1 else 0 end) as n_plus_one_count')
            ->groupBy('uri', 'method')
            ->get();

        $result = $rows->map(function ($row) use ($filters) {
            $count = (int) $row->count;

            return [
                'uri' => $row->uri,
                'method' => $row->method,
                'count' => $count,
                'avg_ms' => round((float) $row->avg_ms, 2),
                'p95_ms' => $this->endpointPercentile($row->uri, $row->method, $filters, 0.95),
                'error_rate' => $count > 0 ? round($row->errors / $count, 4) : 0.0,
                'avg_queries' => round((float) $row->avg_queries, 2),
                'n_plus_one_count' => (int) $row->n_plus_one_count,
            ];
        });

        $sortKey = in_array($sort, ['p95', 'avg', 'count', 'error_rate', 'queries'], true) ? $sort : 'p95';
        $mapKey = match ($sortKey) {
            'p95' => 'p95_ms',
            'avg' => 'avg_ms',
            'count' => 'count',
            'error_rate' => 'error_rate',
            'queries' => 'avg_queries',
        };

        return $result->sortByDesc($mapKey)->take($limit)->values();
    }

    /**
     * Window totals for the overview cards.
     */
    public function overview(array $filters = []): array
    {
        $base = $this->filtered($filters);

        $agg = (clone $base)
            ->selectRaw('count(*) as total')
            ->selectRaw('avg(duration_ms) as avg_ms')
            ->selectRaw('sum(case when status_code >= 500 then 1 else 0 end) as errors')
            ->selectRaw('sum(case when is_slow then 1 else 0 end) as slow')
            ->selectRaw('sum(case when has_n_plus_one then 1 else 0 end) as n_plus_one')
            ->selectRaw('avg(db_query_count) as avg_queries')
            ->first();

        $total = (int) ($agg->total ?? 0);

        return [
            'total_requests' => $total,
            'avg_ms' => round((float) ($agg->avg_ms ?? 0), 2),
            'p95_ms' => $this->percentile((clone $base), 'duration_ms', 0.95),
            'error_rate' => $total > 0 ? round(($agg->errors ?? 0) / $total, 4) : 0.0,
            'slow_count' => (int) ($agg->slow ?? 0),
            'n_plus_one_count' => (int) ($agg->n_plus_one ?? 0),
            'avg_queries' => round((float) ($agg->avg_queries ?? 0), 2),
        ];
    }

    /**
     * Cross-request slow query analysis grouped by sql_hash (Phase 2).
     *
     * @return Collection<int, object>
     */
    public function slowQueries(array $filters = [], int $limit = 50): Collection
    {
        $q = Query::query()->where('is_slow', true);

        if (! empty($filters['from'])) {
            $q->whereHas('profile', fn (Builder $p) => $p->where('created_at', '>=', Carbon::parse($filters['from'])));
        }

        return $q->selectRaw('sql_hash')
            ->selectRaw('count(*) as frequency')
            ->selectRaw('avg(time_ms) as avg_time_ms')
            ->selectRaw('max(time_ms) as max_time_ms')
            ->selectRaw('min(sql) as sample_sql')
            ->groupBy('sql_hash')
            ->orderByDesc('avg_time_ms')
            ->limit($limit)
            ->get();
    }

    /**
     * N+1 suspects in aggregate: profiles flagged has_n_plus_one, grouped by endpoint.
     */
    public function nPlusOneSuspects(array $filters = [], int $limit = 50): Collection
    {
        return $this->filtered($filters)
            ->where('has_n_plus_one', true)
            ->selectRaw('uri, method')
            ->selectRaw('count(*) as occurrences')
            ->selectRaw('avg(db_query_count) as avg_queries')
            ->selectRaw('avg(duration_ms) as avg_ms')
            ->groupBy('uri', 'method')
            ->orderByDesc('occurrences')
            ->limit($limit)
            ->get();
    }

    /* ---------------------------------------------------------------------- */

    protected function filtered(array $filters): Builder
    {
        $q = RequestProfile::query();

        if (! empty($filters['method'])) {
            $q->where('method', strtoupper($filters['method']));
        }
        if (! empty($filters['status'])) {
            $q->where('status_code', (int) $filters['status']);
        }
        if (! empty($filters['slow'])) {
            $q->where('is_slow', true);
        }
        if (! empty($filters['n_plus_one'])) {
            $q->where('has_n_plus_one', true);
        }
        if (! empty($filters['uri'])) {
            $q->where('uri', $filters['uri']);
        }
        if (! empty($filters['from'])) {
            $q->where('created_at', '>=', Carbon::parse($filters['from']));
        }
        if (! empty($filters['to'])) {
            $q->where('created_at', '<=', Carbon::parse($filters['to']));
        }

        return $q;
    }

    /**
     * Exact percentile via ORDER BY + OFFSET — portable across MySQL/Postgres.
     */
    protected function percentile(Builder $query, string $column, float $p): float
    {
        $count = (clone $query)->count();
        if ($count === 0) {
            return 0.0;
        }

        $offset = (int) floor($p * ($count - 1));

        $value = (clone $query)
            ->orderBy($column)
            ->offset($offset)
            ->limit(1)
            ->value($column);

        return round((float) $value, 2);
    }

    protected function endpointPercentile(string $uri, ?string $method, array $filters, float $p): float
    {
        $q = $this->filtered($filters)->where('uri', $uri);
        if ($method !== null) {
            $q->where('method', $method);
        }

        return $this->percentile($q, 'duration_ms', $p);
    }

    protected function perPage(?int $requested): int
    {
        $default = (int) config('apa.api.default_per_page', 25);
        $max = (int) config('apa.api.max_per_page', 200);

        if ($requested === null || $requested <= 0) {
            return $default;
        }

        return min($requested, $max);
    }
}
