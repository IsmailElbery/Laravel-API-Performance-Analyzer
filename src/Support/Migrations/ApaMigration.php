<?php

namespace ApiPerformanceAnalyzer\Support\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Base for all APA migrations. Runs schema operations on the configured profiler
 * connection (apa.storage.connection) so the tables can live on a separate DB
 * from the app being measured. null = default connection.
 */
abstract class ApaMigration extends Migration
{
    protected function connectionName(): ?string
    {
        return config('apa.storage.connection');
    }

    protected function schema()
    {
        return Schema::connection($this->connectionName());
    }
}
