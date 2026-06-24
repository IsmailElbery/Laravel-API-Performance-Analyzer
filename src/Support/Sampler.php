<?php

namespace ApiPerformanceAnalyzer\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Decides, once per request, whether a request is "sampled". The slow/error
 * retention overrides are evaluated at the END of the request (we cannot know
 * duration/status up front), so they live in the middleware — the Sampler only
 * owns the up-front probabilistic decision.
 */
class Sampler
{
    public function __construct(protected Config $config) {}

    public function shouldSample(): bool
    {
        $rate = (float) $this->config->get('apa.sample_rate', 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $rate;
    }
}
