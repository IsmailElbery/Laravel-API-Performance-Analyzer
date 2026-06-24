<?php

namespace ApiPerformanceAnalyzer\Support;

use Illuminate\Http\Request;

/**
 * Normalizes the URI for an endpoint using the MATCHED ROUTE's pattern
 * (e.g. users/{id}) rather than regex-guessing on the raw path. Guessing
 * collapses unrelated paths and inflates counts. Requests with no matched
 * route (404s) bucket as "__unmatched__".
 */
class UriNormalizer
{
    public const UNMATCHED = '__unmatched__';

    public function normalize(Request $request): string
    {
        $route = $request->route();

        if ($route && method_exists($route, 'uri')) {
            $uri = $route->uri();

            return $uri === '' ? '/' : $uri;
        }

        return self::UNMATCHED;
    }

    /**
     * Strip sensitive params from a raw path+query string before storage.
     */
    public function scrubRawUri(Request $request, array $scrubbedParams): string
    {
        $path = $request->getPathInfo();
        $query = $request->getQueryString();

        if ($query === null || $query === '') {
            return $path;
        }

        parse_str($query, $params);

        foreach (array_keys($params) as $key) {
            foreach ($scrubbedParams as $needle) {
                if (strcasecmp($key, $needle) === 0) {
                    $params[$key] = '[scrubbed]';
                }
            }
        }

        $rebuilt = http_build_query($params);

        return $rebuilt === '' ? $path : $path.'?'.$rebuilt;
    }
}
