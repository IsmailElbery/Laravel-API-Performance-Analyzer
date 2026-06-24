<?php

namespace ApiPerformanceAnalyzer\Http\Controllers\Api;

use ApiPerformanceAnalyzer\Http\Controllers\Controller;
use ApiPerformanceAnalyzer\Http\Resources\RequestProfileResource;
use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestsController extends Controller
{
    public function __construct(protected MetricsRepository $metrics) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['method', 'status', 'slow', 'n_plus_one', 'uri', 'from', 'to', 'per_page']);

        $paginator = $this->metrics->requests($filters);

        return RequestProfileResource::collection($paginator)->response();
    }

    public function show(string $uuid): JsonResponse
    {
        $profile = $this->metrics->findByUuid($uuid);

        abort_if($profile === null, 404, 'Profile not found.');

        return (new RequestProfileResource($profile->loadMissing(['queries', 'httpCalls'])))
            ->response();
    }
}
