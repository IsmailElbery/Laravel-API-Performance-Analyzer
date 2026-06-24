<?php

namespace ApiPerformanceAnalyzer\Collectors;

use ApiPerformanceAnalyzer\Contracts\Collector;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Times outbound HTTP calls made through Laravel's HTTP client by registering
 * global request/response middleware (Http::globalRequestMiddleware /
 * globalResponseMiddleware). Outbound calls are sequential per request, so we
 * pair each request with its response via a small pending stack on the context.
 *
 * Requires guzzlehttp/guzzle. Enable by adding this class to apa.collectors.
 */
class HttpCollector implements Collector
{
    protected bool $registered = false;

    public function name(): string
    {
        return 'http';
    }

    public function register(): void
    {
        if ($this->registered || ! class_exists(HttpFactory::class)) {
            return;
        }

        $this->registered = true;

        HttpFactory::globalRequestMiddleware(function (RequestInterface $request) {
            $this->pushPending($request);

            return $request;
        });

        HttpFactory::globalResponseMiddleware(function (ResponseInterface $response) {
            $this->record($response->getStatusCode());

            return $response;
        });
    }

    public function startRequest(Request $request, ProfileContext $context): void
    {
        //
    }

    public function finishRequest(Request $request, Response $response, ProfileContext $context): void
    {
        // Any pending call without a matched response (e.g. it threw) is recorded
        // with a null status so the count stays accurate.
        foreach ($context->httpPending as $pending) {
            $this->commit($context, $pending, null);
        }
        $context->httpPending = [];
    }

    public function reset(): void
    {
        //
    }

    protected function pushPending(RequestInterface $request): void
    {
        if (! app()->bound(ProfileContext::class)) {
            return;
        }

        /** @var ProfileContext $context */
        $context = app(ProfileContext::class);

        $uri = $request->getUri();

        $context->httpPending[] = [
            'host' => $uri->getHost(),
            'method' => $request->getMethod(),
            'url' => (string) $uri,
            'start' => microtime(true),
        ];
    }

    protected function record(int $status): void
    {
        if (! app()->bound(ProfileContext::class)) {
            return;
        }

        /** @var ProfileContext $context */
        $context = app(ProfileContext::class);

        $pending = array_shift($context->httpPending);
        if ($pending === null) {
            return;
        }

        $this->commit($context, $pending, $status);
    }

    protected function commit(ProfileContext $context, array $pending, ?int $status): void
    {
        $duration = isset($pending['start']) ? (microtime(true) - $pending['start']) * 1000 : 0.0;

        $context->externalCallCount++;
        $context->externalTimeMs += $duration;

        $context->addHttpCall([
            'host' => $pending['host'] ?? null,
            'method' => $pending['method'] ?? null,
            'url' => $pending['url'] ?? null,
            'status_code' => $status,
            'duration_ms' => round($duration, 3),
        ]);
    }
}
