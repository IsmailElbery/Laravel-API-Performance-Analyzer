<?php

namespace ApiPerformanceAnalyzer\Storage;

use ApiPerformanceAnalyzer\Contracts\ProfileStore;

/**
 * Writes inline, in the response path. Dev / low-traffic only.
 */
class SyncStore implements ProfileStore
{
    public function __construct(protected ProfileWriter $writer) {}

    public function store(array $profile, array $children): void
    {
        $this->writer->write($profile, $children);
    }
}
