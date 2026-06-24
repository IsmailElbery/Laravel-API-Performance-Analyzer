<?php

namespace ApiPerformanceAnalyzer\Console;

use ApiPerformanceAnalyzer\Analysis\IndexRecommender;
use ApiPerformanceAnalyzer\Analysis\RollupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RollupCommand extends Command
{
    protected $signature = 'apa:rollup {--date= : Day to roll up (Y-m-d, default yesterday)} {--recommend : Also rebuild index recommendations}';

    protected $description = 'Aggregate raw profiles into daily endpoint stats (+ health scores).';

    public function handle(RollupService $service, IndexRecommender $recommender): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();

        $rows = $service->rollup($date);
        $this->info("Rolled up {$rows} endpoint(s) for {$date->toDateString()}.");

        if ($this->option('recommend')) {
            $recs = $recommender->rebuild();
            $this->info("Rebuilt {$recs} index recommendation(s).");
        }

        return self::SUCCESS;
    }
}
