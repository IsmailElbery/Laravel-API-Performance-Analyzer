<?php

namespace ApiPerformanceAnalyzer\Http\Controllers;

use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected MetricsRepository $metrics) {}

    public function index(Request $request): View
    {
        $filters = $this->windowFilters($request);

        return view('apa::dashboard.index', [
            'overview' => $this->metrics->overview($filters),
            'slowEndpoints' => $this->metrics->endpoints($filters, 'p95', 10),
            'window' => $filters,
        ]);
    }

    public function endpoints(Request $request): View
    {
        $filters = $this->windowFilters($request, ['method', 'slow']);
        $sort = (string) $request->query('sort', 'p95');

        return view('apa::dashboard.endpoints', [
            'endpoints' => $this->metrics->endpoints($filters, $sort, 100),
            'sort' => $sort,
            'window' => $filters,
        ]);
    }

    public function nPlusOne(Request $request): View
    {
        $filters = $this->windowFilters($request);

        return view('apa::dashboard.n-plus-one', [
            'suspects' => $this->metrics->nPlusOneSuspects($filters, 100),
            'window' => $filters,
        ]);
    }

    public function slowQueries(Request $request): View
    {
        $filters = $this->windowFilters($request);

        return view('apa::dashboard.slow-queries', [
            'queries' => $this->metrics->slowQueries($filters, 100),
            'window' => $filters,
        ]);
    }

    public function show(string $uuid): View
    {
        $profile = $this->metrics->findByUuid($uuid);

        abort_if($profile === null, 404);

        return view('apa::dashboard.show', ['profile' => $profile]);
    }

    /**
     * Build the request-list view with its own filters + pagination.
     */
    public function requests(Request $request): View
    {
        $filters = $request->only(['method', 'status', 'slow', 'uri', 'from', 'to', 'per_page']);

        return view('apa::dashboard.requests', [
            'requests' => $this->metrics->requests($filters),
            'filters' => $filters,
        ]);
    }

    /** Default window: last 24h unless from/to provided. */
    protected function windowFilters(Request $request, array $extra = []): array
    {
        $filters = [
            'from' => $request->query('from', now()->subDay()->toDateTimeString()),
            'to' => $request->query('to'),
        ];

        foreach ($extra as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->query($key);
            }
        }

        return array_filter($filters, fn ($v) => $v !== null);
    }
}
