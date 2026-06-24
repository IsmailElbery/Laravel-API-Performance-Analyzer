<?php

namespace ApiPerformanceAnalyzer\Console;

use ApiPerformanceAnalyzer\Models\Query;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs EXPLAIN for a captured slow query, BY HASH, gated to non-production.
 *
 * This is the only place the package replays a statement against the database,
 * and it is deliberately opt-in and dev/staging-only: replaying in production
 * adds load and bindings may be stale/absent. Never auto-applied.
 */
class ExplainCommand extends Command
{
    protected $signature = 'apa:explain {sql_hash : sql_hash of the captured query}
                            {--connection= : Connection to EXPLAIN against}
                            {--force : Allow running in production (discouraged)}';

    protected $description = 'EXPLAIN a captured slow query (dev/staging only).';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run EXPLAIN in production. Use --force only if you understand the load implications.');

            return self::FAILURE;
        }

        $query = Query::query()->where('sql_hash', $this->argument('sql_hash'))->first();

        if (! $query) {
            $this->error('No captured query with that sql_hash.');

            return self::FAILURE;
        }

        $sql = $query->sql;
        if (! preg_match('/^\s*select/i', $sql)) {
            $this->error('Only SELECT statements are EXPLAINed.');

            return self::FAILURE;
        }

        $connection = $this->option('connection') ?: $query->connection;
        $conn = DB::connection($connection ?: null);
        $driver = $conn->getDriverName();

        $this->line("Query: <comment>{$sql}</comment>");
        $this->line("Connection: {$connection} ({$driver})");
        $this->newLine();

        try {
            $explainSql = $driver === 'pgsql'
                ? 'EXPLAIN (FORMAT JSON) '.$sql
                : 'EXPLAIN '.$sql;

            // No bindings replayed — placeholders are explained as-is where supported.
            $rows = $conn->select($explainSql);

            foreach ($rows as $row) {
                $this->line(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        } catch (\Throwable $e) {
            $this->error('EXPLAIN failed: '.$e->getMessage());
            $this->line('This often means the statement has placeholders/bindings that cannot be replayed. Recommendations remain advisory.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
