<?php

namespace ApiPerformanceAnalyzer\Console;

use ApiPerformanceAnalyzer\Alerts\AlertEvaluator;
use Illuminate\Console\Command;

class AlertsCommand extends Command
{
    protected $signature = 'apa:alerts';

    protected $description = 'Evaluate alert rules and dispatch notifications for any that fire.';

    public function handle(AlertEvaluator $evaluator): int
    {
        if (! config('apa.alerts.enabled', false)) {
            $this->warn('Alerts are disabled (apa.alerts.enabled = false).');

            return self::SUCCESS;
        }

        $fired = $evaluator->evaluate();
        $this->info("Evaluated rules; {$fired->count()} alert(s) fired.");

        return self::SUCCESS;
    }
}
