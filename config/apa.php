<?php

use ApiPerformanceAnalyzer\Collectors\MemoryCollector;
use ApiPerformanceAnalyzer\Collectors\QueryCollector;
use ApiPerformanceAnalyzer\Collectors\ResponseCollector;
use ApiPerformanceAnalyzer\Collectors\TimingCollector;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | `enabled` is the static (deploy-time) switch. `kill_switch_cache_key` is
    | a per-request cache flag checked on every request so profiling can be
    | disabled instantly during an incident WITHOUT a deploy.
    */
    'enabled' => env('APA_ENABLED', true),
    'kill_switch_cache_key' => 'apa:disabled',

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    | The sample decision is made once at request start. Lightweight capture
    | (timing/status) always runs; full retention (with child rows) happens
    | when: sampled OR (always_capture_slow AND slow) OR (always_capture_errors
    | AND error). See Support\Sampler / ApaMiddleware.
    */
    'sample_rate' => (float) env('APA_SAMPLE_RATE', 1.0), // 0.0 - 1.0
    'always_capture_errors' => env('APA_CAPTURE_ERRORS', true),
    'always_capture_slow' => env('APA_CAPTURE_SLOW', true),

    /*
    |--------------------------------------------------------------------------
    | Route matching
    |--------------------------------------------------------------------------
    | Only capture requests whose URI matches one of these patterns. Empty means
    | "capture everything that hits the middleware". `except` always wins.
    */
    'only_paths' => [],          // e.g. ['api/*']
    'except_paths' => [
        'apa',
        'apa/*',
        'telescope*',
        'horizon*',
        '_debugbar*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane
    |--------------------------------------------------------------------------
    */
    'octane' => [
        'reset_context_per_request' => true, // bind ProfileContext fresh each request
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'slow_request_ms' => (int) env('APA_SLOW_REQUEST_MS', 500),
        'slow_query_ms' => (int) env('APA_SLOW_QUERY_MS', 100),
        'n_plus_one' => (int) env('APA_N_PLUS_ONE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    | driver: sync (inline, dev only) | queue (one job per retained request) |
    |         batch (buffer + bulk insert on interval/size, best for high traffic)
    |
    | connection: a SEPARATE DB connection is strongly recommended in prod so the
    |   profiler's own writes never pollute the metrics it measures and never
    |   contend with app traffic during a spike. null = default connection.
    */
    'storage' => [
        'driver' => env('APA_DRIVER', 'queue'),
        'connection' => env('APA_DB_CONNECTION', null),
        'queue' => env('APA_QUEUE', 'default'),
        'queue_connection' => env('APA_QUEUE_CONNECTION', null),
        'retention_days' => (int) env('APA_RETENTION_DAYS', 14),

        // Which profiles also persist child rows (queries / http calls).
        'store_children_for' => ['slow', 'error', 'sampled'],

        'batch' => [
            'buffer' => env('APA_BUFFER', 'redis'), // redis | cache
            'cache_store' => env('APA_BUFFER_STORE', null),
            'flush_every_seconds' => (int) env('APA_FLUSH_SECONDS', 10),
            'flush_at_size' => (int) env('APA_FLUSH_SIZE', 200),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    */
    'privacy' => [
        'store_bindings' => env('APA_STORE_BINDINGS', false), // bindings can be PII — off by default
        'hash_ip' => env('APA_HASH_IP', true),
        'scrub_query_string' => true,
        'scrubbed_params' => ['token', 'key', 'secret', 'password', 'api_key', 'access_token'],
        'sql_max_length' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collectors
    |--------------------------------------------------------------------------
    | Each owns one metric area and implements Contracts\Collector. Disabled
    | (omitted) collectors cost nothing.
    */
    'collectors' => [
        TimingCollector::class,
        QueryCollector::class,
        MemoryCollector::class,
        ResponseCollector::class,
        // Phase 2 — enable when guzzlehttp/guzzle is installed:
        // \ApiPerformanceAnalyzer\Collectors\HttpCollector::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard (admin-gated — never leave open)
    |--------------------------------------------------------------------------
    | `gate` is the Gate ability checked in addition to the middleware. Define
    | it in your app (Gate::define('viewApa', ...)); by default in the local
    | environment access is open and elsewhere it is denied unless you define it.
    */
    'dashboard' => [
        'enabled' => env('APA_DASHBOARD', true),
        'path' => 'apa',
        'middleware' => ['web', 'auth'],
        'gate' => 'viewApa',
    ],

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => env('APA_API', true),
        'path' => 'apa/api',
        'middleware' => ['api', 'auth:sanctum'],
        'gate' => 'viewApa',
        'default_per_page' => 25,
        'max_per_page' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 3 — health scoring, alerts, reports
    |--------------------------------------------------------------------------
    */
    'health' => [
        'min_samples' => (int) env('APA_HEALTH_MIN_SAMPLES', 50),
        // Weights for: p95, error rate, query count, n+1 rate.
        'weights' => ['p95' => 0.4, 'error_rate' => 0.3, 'query_count' => 0.2, 'n_plus_one' => 0.1],
        // Normalization ceilings (value at/above this scores the full penalty).
        'norm' => ['p95_ms' => 2000, 'query_count' => 50],
    ],

    'alerts' => [
        'enabled' => env('APA_ALERTS', false),
        'channels' => ['mail', 'slack'],
        'mail_to' => env('APA_ALERT_MAIL', null),
        'slack_webhook' => env('APA_SLACK_WEBHOOK', null),
        'default_window_minutes' => 60,
        'default_cooldown_minutes' => 60,
    ],

    'reports' => [
        'enabled' => env('APA_REPORTS', false),
        'driver' => 'dompdf',
    ],
];
