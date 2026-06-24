<?php

namespace ApiPerformanceAnalyzer\Contracts;

use ApiPerformanceAnalyzer\Support\ProfileContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Collector owns exactly one metric area (timing, queries, memory, http...).
 *
 * Lifecycle, per request:
 *   - register()  once at boot (e.g. attach a DB::listen). May be a no-op.
 *   - startRequest()  when the request enters the middleware.
 *   - finishRequest() before the response is sent / in terminate(); writes its
 *                     contribution into the shared ProfileContext.
 *
 * Implementations MUST hold no cross-request state of their own (Octane-safe):
 * all accumulation lives in the per-request ProfileContext.
 */
interface Collector
{
    /** Stable key used in config / context (e.g. "timing", "query"). */
    public function name(): string;

    /** Attach process-level listeners once. Safe to call repeatedly under Octane. */
    public function register(): void;

    /** Begin capturing for this request. */
    public function startRequest(Request $request, ProfileContext $context): void;

    /** Flush this collector's metrics into the context. */
    public function finishRequest(Request $request, Response $response, ProfileContext $context): void;

    /** Reset any per-request scratch state held by the collector itself. */
    public function reset(): void;
}
