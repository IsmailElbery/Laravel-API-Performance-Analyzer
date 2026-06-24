<?php

namespace ApiPerformanceAnalyzer\Collectors;

use ApiPerformanceAnalyzer\Contracts\Collector;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use ApiPerformanceAnalyzer\Support\UriNormalizer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseCollector implements Collector
{
    public function __construct(
        protected Config $config,
        protected UriNormalizer $normalizer,
    ) {}

    public function name(): string
    {
        return 'response';
    }

    public function register(): void
    {
        //
    }

    public function startRequest(Request $request, ProfileContext $context): void
    {
        $context->method = $request->getMethod();

        $scrub = (bool) $this->config->get('apa.privacy.scrub_query_string', true);
        $params = (array) $this->config->get('apa.privacy.scrubbed_params', []);

        $context->rawUri = $scrub
            ? $this->normalizer->scrubRawUri($request, $params)
            : $request->getRequestUri();
    }

    public function finishRequest(Request $request, Response $response, ProfileContext $context): void
    {
        $context->statusCode = $response->getStatusCode();
        $context->uri = $this->normalizer->normalize($request);

        if ($route = $request->route()) {
            $context->routeName = method_exists($route, 'getName') ? $route->getName() : null;
        }

        $context->isError = $context->statusCode >= 500
            || $context->statusCode === 0; // unhandled / aborted

        // user id, if authenticated
        if ($user = $request->user()) {
            $context->userId = method_exists($user, 'getAuthIdentifier')
                ? (int) $user->getAuthIdentifier()
                : null;
        }

        $context->ip = $this->resolveIp($request);
    }

    public function reset(): void
    {
        //
    }

    protected function resolveIp(Request $request): ?string
    {
        $ip = $request->ip();

        if ($ip === null) {
            return null;
        }

        if ((bool) $this->config->get('apa.privacy.hash_ip', true)) {
            return substr(hash('sha256', $ip), 0, 32);
        }

        return $ip;
    }
}
