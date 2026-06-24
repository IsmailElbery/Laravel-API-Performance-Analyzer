# API Performance Analyzer

A production-safe API performance profiler for Laravel — in the spirit of Telescope/Pulse but focused exclusively on API performance. Low-overhead capture, an admin-gated dashboard, and a REST API for every metric.

- **Package:** `ismail/api-performance-analyzer`
- **Namespace:** `ApiPerformanceAnalyzer\`
- **Requires:** PHP 8.2+, Laravel 11/12, MySQL or PostgreSQL
- **Vendor prefix:** `apa`

## Install

```bash
composer require ismail/api-performance-analyzer

# Publish config (optional) and run migrations
php artisan vendor:publish --tag=apa-config
php artisan migrate
```

The middleware auto-attaches to your `api` route group — no code changes needed. The dashboard lives at `/apa` and the REST API at `/apa/api`.

## How capture works

```
Request → ApaMiddleware → [Collectors] → ProfileContext (in-memory)
                                              │ terminate()
                                              ▼
                                  ProfileStore (sync|queue|batch)
                                              ▼
                              apa_request_profiles (+ child rows)
```

- **Lightweight always-on capture** (timing/status) runs for every request.
- **Retention** is decided at the *end* of the request: a profile is kept when it is `sampled` **or** slow **or** an error. Child rows (queries, HTTP calls) are only stored for retained profiles.
- All writes happen in `terminate()` — never in the response path.

## Configuration highlights (`config/apa.php`)

| Key | Purpose |
|---|---|
| `sample_rate` | Probabilistic capture (0.0–1.0). Errors/slow always retained. |
| `kill_switch_cache_key` | Set this cache key truthy to disable capture instantly — no deploy. |
| `storage.driver` | `sync` (dev), `queue` (default), `batch` (high-traffic bulk insert). |
| `storage.connection` | **Use a separate DB connection in production** so the profiler never pollutes or contends with the app it measures. |
| `thresholds.*` | `slow_request_ms`, `slow_query_ms`, `n_plus_one`. |
| `privacy.*` | Bindings off by default (PII), IP hashing, query-string scrubbing. |
| `dashboard.middleware` + `dashboard.gate` | Admin gate — never left open. Define `Gate::define('viewApa', …)`. |

### Storage drivers

- **`sync`** — inline write. Dev / low traffic only.
- **`queue`** — one `StoreProfileJob` per retained request. Default.
- **`batch`** — buffer in Redis and bulk-insert on size or via `apa:flush` (schedule it). Recommended for high traffic.

## REST API

```
GET /apa/api/requests              list + filters (method,status,slow,n_plus_one,uri,from,to,per_page)
GET /apa/api/requests/{uuid}       single profile + queries + http calls
GET /apa/api/endpoints             per-endpoint stats (count, avg, p95, error_rate, n+1)  ?sort=p95|avg|count|error_rate|queries
GET /apa/api/stats/overview        window totals
GET /apa/api/stats/slow-queries    cross-request slow query analysis (by sql_hash)
GET /apa/api/stats/n-plus-one      aggregate N+1 suspects per endpoint
```

## Commands

| Command | What it does |
|---|---|
| `apa:prune [--days=]` | Delete profiles past the retention window. Schedule daily. |
| `apa:rollup [--date=] [--recommend]` | Aggregate into `apa_daily_endpoint_stats` (+ health scores). Schedule daily. |
| `apa:flush` | Flush the `batch` buffer. Schedule every minute when using batch. |
| `apa:alerts` | Evaluate alert rules and notify (Slack/email). Schedule. |
| `apa:explain {sql_hash}` | EXPLAIN a captured slow query — **dev/staging only**, opt-in, never auto-applied. |
| `apa:report [--from=] [--to=] [--email=] [--path=]` | One-document PDF digest (problems + adjacent recommendations). Requires `barryvdh/laravel-dompdf`. |

### Suggested scheduler

```php
$schedule->command('apa:prune')->daily();
$schedule->command('apa:rollup --recommend')->dailyAt('00:30');
$schedule->command('apa:flush')->everyMinute();      // only with the batch driver
$schedule->command('apa:alerts')->everyFiveMinutes(); // only if alerts enabled
```

## Phases

- **Phase 1** — timing, query count/time, memory, status; slow-endpoint ranking; request list/detail; REST API.
- **Phase 2** — N+1 detection (per-request and aggregate), external HTTP timing (`HttpCollector`), daily rollups, slow-query analysis.
- **Phase 3** — per-endpoint health scores (with insufficient-data guardrails), advisory index recommendations, threshold alert engine with cooldown, Slack/email notifications, PDF digest.

## Production notes

- Run profiler storage on a **separate connection**; use the `batch` driver under heavy load.
- Sampling decides *what* to capture; the `batch` driver decides *how cheaply* to write it.
- The per-request cache kill switch disables capture during an incident without a deploy.
- Octane-safe: `ProfileContext` is a scoped binding reset on every request; collectors hold no cross-request state.

## Tests

```bash
composer install
vendor/bin/phpunit
```
