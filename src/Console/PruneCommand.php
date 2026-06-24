<?php

namespace ApiPerformanceAnalyzer\Console;

use ApiPerformanceAnalyzer\Models\HttpCall;
use ApiPerformanceAnalyzer\Models\Query;
use ApiPerformanceAnalyzer\Models\RequestProfile;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'apa:prune {--days= : Override retention_days}';

    protected $description = 'Delete profiler rows older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('apa.storage.retention_days', 14));
        $cutoff = now()->subDays($days);

        $profileIds = RequestProfile::query()
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($profileIds->isEmpty()) {
            $this->info("Nothing to prune (retention {$days}d).");

            return self::SUCCESS;
        }

        $profileIds->chunk(1000)->each(function ($chunk) {
            Query::query()->whereIn('profile_id', $chunk)->delete();
            HttpCall::query()->whereIn('profile_id', $chunk)->delete();
            RequestProfile::query()->whereIn('id', $chunk)->delete();
        });

        $this->info("Pruned {$profileIds->count()} profiles older than {$days}d.");

        return self::SUCCESS;
    }
}
