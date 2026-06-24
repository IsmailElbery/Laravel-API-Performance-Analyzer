<?php

namespace ApiPerformanceAnalyzer\Contracts;

/**
 * Decides HOW a finished profile is persisted: inline (sync), via a queued job
 * (queue), or buffered for bulk insert (batch). The actual SQL lives in
 * Storage\ProfileWriter, shared by all drivers.
 */
interface ProfileStore
{
    /**
     * Persist (or schedule/buffer persistence of) one finished profile.
     *
     * @param  array  $profile   parent row (from ProfileContext::toProfileArray)
     * @param  array  $children  ['queries' => [...], 'http_calls' => [...]] — only when retained
     */
    public function store(array $profile, array $children): void;
}
