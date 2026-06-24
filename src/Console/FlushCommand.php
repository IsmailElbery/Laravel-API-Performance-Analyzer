<?php

namespace ApiPerformanceAnalyzer\Console;

use ApiPerformanceAnalyzer\Storage\BatchStore;
use Illuminate\Console\Command;

class FlushCommand extends Command
{
    protected $signature = 'apa:flush';

    protected $description = 'Flush the batch storage buffer to the database (batch driver).';

    public function handle(): int
    {
        if (config('apa.storage.driver') !== 'batch') {
            $this->warn('Storage driver is not "batch"; nothing to flush.');

            return self::SUCCESS;
        }

        $count = app(BatchStore::class)->flush();
        $this->info("Flushed {$count} buffered profile(s).");

        return self::SUCCESS;
    }
}
