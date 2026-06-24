<?php

namespace ApiPerformanceAnalyzer\Collectors;

use ApiPerformanceAnalyzer\Contracts\Collector;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemoryCollector implements Collector
{
    public function name(): string
    {
        return 'memory';
    }

    public function register(): void
    {
        //
    }

    public function startRequest(Request $request, ProfileContext $context): void
    {
        //
    }

    public function finishRequest(Request $request, Response $response, ProfileContext $context): void
    {
        $context->peakMemoryKb = (int) round(memory_get_peak_usage(true) / 1024);
    }

    public function reset(): void
    {
        //
    }
}
