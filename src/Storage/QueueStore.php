<?php

namespace ApiPerformanceAnalyzer\Storage;

use ApiPerformanceAnalyzer\Contracts\ProfileStore;
use ApiPerformanceAnalyzer\Jobs\StoreProfileJob;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * One queued StoreProfileJob per retained request. Default driver: simple, keeps
 * writes off the response path.
 */
class QueueStore implements ProfileStore
{
    public function __construct(protected Config $config) {}

    public function store(array $profile, array $children): void
    {
        $job = new StoreProfileJob($profile, $children);

        if ($connection = $this->config->get('apa.storage.queue_connection')) {
            $job->onConnection($connection);
        }

        $job->onQueue($this->config->get('apa.storage.queue', 'default'));

        dispatch($job);
    }
}
