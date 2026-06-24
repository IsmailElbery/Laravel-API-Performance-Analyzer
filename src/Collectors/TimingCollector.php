<?php

namespace ApiPerformanceAnalyzer\Collectors;

use ApiPerformanceAnalyzer\Contracts\Collector;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimingCollector implements Collector
{
    public function name(): string
    {
        return 'timing';
    }

    public function register(): void
    {
        // No process-level listeners.
    }

    public function startRequest(Request $request, ProfileContext $context): void
    {
        // Prefer the true request start (Laravel sets LARAVEL_START); fall back
        // to now so the middleware boot time is the floor.
        $context->startedAt = defined('LARAVEL_START')
            ? (float) LARAVEL_START
            : microtime(true);
    }

    public function finishRequest(Request $request, Response $response, ProfileContext $context): void
    {
        $start = $context->startedAt > 0.0 ? $context->startedAt : microtime(true);
        $context->durationMs = (microtime(true) - $start) * 1000;
    }

    public function reset(): void
    {
        // Stateless.
    }
}
