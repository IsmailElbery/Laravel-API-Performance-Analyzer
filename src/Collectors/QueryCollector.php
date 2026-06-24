<?php

namespace ApiPerformanceAnalyzer\Collectors;

use ApiPerformanceAnalyzer\Contracts\Collector;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts queries and sums their time via DB::listen, flagging slow ones. Queries
 * run on the profiler's own storage connection are ignored so the profiler never
 * measures itself (important on the single-connection dev path; in prod the
 * separate connection makes this moot but the guard is cheap and correct).
 *
 * The listener writes into the *current* request's ProfileContext, resolved lazily
 * from the container so this collector holds no cross-request state (Octane-safe).
 */
class QueryCollector implements Collector
{
    protected bool $listening = false;

    public function __construct(protected Config $config) {}

    public function name(): string
    {
        return 'query';
    }

    public function register(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        $storageConnection = $this->config->get('apa.storage.connection');
        $slowMs = (int) $this->config->get('apa.thresholds.slow_query_ms', 100);
        $storeBindings = (bool) $this->config->get('apa.privacy.store_bindings', false);
        $maxLen = (int) $this->config->get('apa.privacy.sql_max_length', 2000);

        DB::listen(function (QueryExecuted $event) use ($storageConnection, $slowMs, $storeBindings, $maxLen) {
            // Never profile the profiler's own writes.
            if ($storageConnection !== null && $event->connectionName === $storageConnection) {
                return;
            }

            if (! app()->bound(ProfileContext::class)) {
                return;
            }

            /** @var ProfileContext $context */
            $context = app(ProfileContext::class);

            $context->dbQueryCount++;
            $context->dbTimeMs += $event->time;

            $sql = $this->normalizeSql($event->sql, $maxLen);

            $context->addQuery([
                'sql_hash' => sha1($sql),
                'sql' => $sql,
                'bindings_count' => count($event->bindings),
                'bindings' => $storeBindings ? $this->safeBindings($event->bindings) : null,
                'time_ms' => round($event->time, 3),
                'connection' => $event->connectionName,
                'is_slow' => $event->time >= $slowMs,
            ]);
        });
    }

    public function startRequest(Request $request, ProfileContext $context): void
    {
        // Counters live on the context, already fresh per request.
    }

    public function finishRequest(Request $request, Response $response, ProfileContext $context): void
    {
        // Nothing to flush — accumulated live via the listener.
    }

    public function reset(): void
    {
        // Listener targets the per-request context; nothing to reset here.
    }

    /** Collapse whitespace and truncate; we store a representative statement, not every byte. */
    protected function normalizeSql(string $sql, int $maxLen): string
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);

        if (strlen($sql) > $maxLen) {
            $sql = substr($sql, 0, $maxLen).'…';
        }

        return $sql;
    }

    protected function safeBindings(array $bindings): array
    {
        return array_map(function ($b) {
            if (is_scalar($b) || $b === null) {
                return $b;
            }

            return is_object($b) ? get_class($b) : gettype($b);
        }, $bindings);
    }
}
