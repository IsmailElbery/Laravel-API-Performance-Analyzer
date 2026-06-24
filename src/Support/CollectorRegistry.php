<?php

namespace ApiPerformanceAnalyzer\Support;

use ApiPerformanceAnalyzer\Contracts\Collector;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves and holds the configured collector instances (one set per process)
 * and wires their process-level listeners once. The middleware iterates the same
 * instances per request. Collectors hold no cross-request state, so sharing the
 * instances is Octane-safe.
 */
class CollectorRegistry
{
    /** @var array<int, Collector> */
    protected array $collectors = [];

    protected bool $registered = false;

    public function __construct(
        protected Container $container,
        protected Config $config,
    ) {}

    /** @return array<int, Collector> */
    public function all(): array
    {
        if ($this->collectors === []) {
            foreach ((array) $this->config->get('apa.collectors', []) as $class) {
                $this->collectors[] = $this->container->make($class);
            }
        }

        return $this->collectors;
    }

    /** Attach each collector's process-level listeners once. */
    public function bootListeners(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        foreach ($this->all() as $collector) {
            $collector->register();
        }
    }
}
