<?php

namespace ApiPerformanceAnalyzer\Http\Middleware;

use ApiPerformanceAnalyzer\Contracts\ProfileStore;
use ApiPerformanceAnalyzer\Detection\NPlusOneDetector;
use ApiPerformanceAnalyzer\Support\CollectorRegistry;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use ApiPerformanceAnalyzer\Support\Sampler;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The capture entry point. Adds itself to the `api` group.
 *
 * Flow:
 *   handle()      — gate checks (enabled, kill switch, path filter), sample
 *                   decision, start collectors.
 *   terminate()   — finish collectors, compute slow/error, run N+1, decide
 *                   RETENTION (sampled || slow || error), build children only
 *                   when retained, hand to the configured ProfileStore.
 *
 * All persistence happens in terminate(), never in the response path.
 */
class ApaMiddleware
{
    protected bool $active = false;

    public function __construct(
        protected Container $container,
        protected Config $config,
        protected CollectorRegistry $registry,
        protected Sampler $sampler,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldProfile($request)) {
            return $next($request);
        }

        $this->active = true;

        /** @var ProfileContext $context */
        $context = $this->container->make(ProfileContext::class);
        $context->sampled = $this->sampler->shouldSample();

        foreach ($this->registry->all() as $collector) {
            $collector->startRequest($request, $context);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->active) {
            return;
        }

        try {
            $this->capture($request, $response);
        } catch (Throwable $e) {
            // Profiling must never break the app. Swallow, optionally log.
            report($e);
        } finally {
            $this->active = false;
        }
    }

    protected function capture(Request $request, Response $response): void
    {
        /** @var ProfileContext $context */
        $context = $this->container->make(ProfileContext::class);

        foreach ($this->registry->all() as $collector) {
            $collector->finishRequest($request, $response, $context);
        }

        // Slow / error are now knowable — fold into the retention decision.
        $slowMs = (int) $this->config->get('apa.thresholds.slow_request_ms', 500);
        $context->isSlow = $context->durationMs >= $slowMs;

        if (! $context->isError) {
            $context->isError = $context->statusCode !== null && $context->statusCode >= 500;
        }

        // N+1 detection (Phase 2) — only meaningful when we have child queries.
        (new NPlusOneDetector((int) $this->config->get('apa.thresholds.n_plus_one', 5)))
            ->inspect($context);

        $retainErrors = (bool) $this->config->get('apa.always_capture_errors', true);
        $retainSlow = (bool) $this->config->get('apa.always_capture_slow', true);

        $retained = $context->sampled
            || ($retainSlow && $context->isSlow)
            || ($retainErrors && $context->isError);

        if (! $retained) {
            return; // counted toward sampling math only; nothing persisted.
        }

        $children = $this->buildChildren($context);

        $this->container->make(ProfileStore::class)
            ->store($context->toProfileArray(), $children);
    }

    /**
     * Child rows are only persisted for the categories in store_children_for.
     */
    protected function buildChildren(ProfileContext $context): array
    {
        $categories = (array) $this->config->get('apa.storage.store_children_for', ['slow', 'error', 'sampled']);

        $wants = (in_array('slow', $categories, true) && $context->isSlow)
            || (in_array('error', $categories, true) && $context->isError)
            || (in_array('sampled', $categories, true) && $context->sampled);

        if (! $wants) {
            return [];
        }

        return [
            'queries' => $context->queries,
            'http_calls' => $context->httpCalls,
        ];
    }

    protected function shouldProfile(Request $request): bool
    {
        if (! (bool) $this->config->get('apa.enabled', true)) {
            return false;
        }

        // Per-request kill switch — cheap cache read, no deploy needed.
        $killKey = $this->config->get('apa.kill_switch_cache_key', 'apa:disabled');
        if ($killKey && Cache::get($killKey)) {
            return false;
        }

        $path = $request->path();

        foreach ((array) $this->config->get('apa.except_paths', []) as $pattern) {
            if (Str::is($pattern, $path)) {
                return false;
            }
        }

        $only = (array) $this->config->get('apa.only_paths', []);
        if ($only !== []) {
            foreach ($only as $pattern) {
                if (Str::is($pattern, $path)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
