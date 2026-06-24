<?php

namespace ApiPerformanceAnalyzer\Http\Controllers\Api;

use ApiPerformanceAnalyzer\Http\Controllers\Controller;
use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndpointsController extends Controller
{
    public function __construct(protected MetricsRepository $metrics) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['method', 'status', 'slow', 'from', 'to']);
        $sort = (string) $request->query('sort', 'p95');
        $limit = (int) $request->query('limit', 50);

        return response()->json([
            'data' => $this->metrics->endpoints($filters, $sort, $limit),
        ]);
    }
}
