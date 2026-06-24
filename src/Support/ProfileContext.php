<?php

namespace ApiPerformanceAnalyzer\Support;

use Illuminate\Support\Str;

/**
 * Per-request accumulator. One instance per HTTP request.
 *
 * Under Octane this MUST be reset/rebound per request — it is registered as a
 * scoped binding in the service provider and reset on RequestReceived. It never
 * holds state across requests.
 */
class ProfileContext
{
    public string $uuid;

    /* Set at request start. */
    public float $startedAt = 0.0;       // microtime(true)
    public ?string $method = null;
    public ?string $rawUri = null;

    /* Filled by collectors during/at finish. */
    public ?string $routeName = null;
    public ?string $uri = null;          // normalized pattern, e.g. users/{id}
    public ?int $statusCode = null;
    public float $durationMs = 0.0;
    public int $peakMemoryKb = 0;

    public int $dbQueryCount = 0;
    public float $dbTimeMs = 0.0;

    /* Child rows — only persisted when the profile is "retained" with children. */
    /** @var array<int, array<string, mixed>> */
    public array $queries = [];
    /** @var array<int, array<string, mixed>> */
    public array $httpCalls = [];

    /** Transient: outbound calls awaiting their response (HttpCollector). */
    /** @var array<int, array<string, mixed>> */
    public array $httpPending = [];

    public int $externalCallCount = 0;
    public float $externalTimeMs = 0.0;

    /* N+1 (Phase 2). */
    public bool $hasNPlusOne = false;
    /** @var array<string, array{sql:string, count:int}> */
    public array $nPlusOneSuspects = [];

    /* Sampling / retention decision. */
    public bool $sampled = false;
    public bool $isSlow = false;
    public bool $isError = false;

    public ?int $userId = null;
    public ?string $ip = null;

    public function __construct()
    {
        $this->uuid = (string) Str::uuid();
    }

    /** Reset to a pristine state for reuse (Octane request boundary). */
    public function reset(): void
    {
        $this->uuid = (string) Str::uuid();
        $this->startedAt = 0.0;
        $this->method = null;
        $this->rawUri = null;
        $this->routeName = null;
        $this->uri = null;
        $this->statusCode = null;
        $this->durationMs = 0.0;
        $this->peakMemoryKb = 0;
        $this->dbQueryCount = 0;
        $this->dbTimeMs = 0.0;
        $this->queries = [];
        $this->httpCalls = [];
        $this->httpPending = [];
        $this->externalCallCount = 0;
        $this->externalTimeMs = 0.0;
        $this->hasNPlusOne = false;
        $this->nPlusOneSuspects = [];
        $this->sampled = false;
        $this->isSlow = false;
        $this->isError = false;
        $this->userId = null;
        $this->ip = null;
    }

    public function addQuery(array $query): void
    {
        $this->queries[] = $query;
    }

    public function addHttpCall(array $call): void
    {
        $this->httpCalls[] = $call;
    }

    /**
     * Is this profile retained at all? (Anything we keep beyond aggregate counters.)
     */
    public function isRetained(): bool
    {
        return $this->sampled || $this->isSlow || $this->isError;
    }

    /** The parent-row payload for persistence. */
    public function toProfileArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'method' => $this->method,
            'route_name' => $this->routeName,
            'uri' => $this->uri,
            'raw_uri' => $this->rawUri,
            'status_code' => $this->statusCode,
            'duration_ms' => round($this->durationMs, 3),
            'db_query_count' => $this->dbQueryCount,
            'db_time_ms' => round($this->dbTimeMs, 3),
            'peak_memory_kb' => $this->peakMemoryKb,
            'is_slow' => $this->isSlow,
            'sampled' => $this->sampled,
            'has_n_plus_one' => $this->hasNPlusOne,
            'external_call_count' => $this->externalCallCount,
            'external_time_ms' => round($this->externalTimeMs, 3),
            'user_id' => $this->userId,
            'ip' => $this->ip,
            'created_at' => now(),
        ];
    }
}
