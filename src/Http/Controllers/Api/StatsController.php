<?php

namespace ApiPerformanceAnalyzer\Http\Controllers\Api;

use ApiPerformanceAnalyzer\Http\Controllers\Controller;
use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(protected MetricsRepository $metrics) {}

    public function overview(Request $request): JsonResponse
    {
        $filters = $request->only(['method', 'from', 'to']);

        return response()->json([
            'data' => $this->metrics->overview($filters),
        ]);
    }

    public function slowQueries(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to']);

        return response()->json([
            'data' => $this->metrics->slowQueries($filters, (int) $request->query('limit', 50)),
        ]);
    }

    public function nPlusOne(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to']);

        return response()->json([
            'data' => $this->metrics->nPlusOneSuspects($filters, (int) $request->query('limit', 50)),
        ]);
    }
}
