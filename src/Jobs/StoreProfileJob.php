<?php

namespace ApiPerformanceAnalyzer\Jobs;

use ApiPerformanceAnalyzer\Storage\ProfileWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreProfileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public array $profile,
        public array $children = [],
    ) {}

    public function handle(ProfileWriter $writer): void
    {
        $writer->write($this->profile, $this->children);
    }
}
