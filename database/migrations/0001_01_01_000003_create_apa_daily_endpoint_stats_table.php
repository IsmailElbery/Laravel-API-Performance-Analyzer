<?php

use ApiPerformanceAnalyzer\Support\Migrations\ApaMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends ApaMigration
{
    public function up(): void
    {
        $this->schema()->create('apa_daily_endpoint_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->string('uri');
            $table->string('method', 10)->nullable();
            $table->integer('count')->default(0);
            $table->float('avg_ms')->default(0);
            $table->float('p95_ms')->default(0);
            $table->float('error_rate')->default(0);     // 0..1
            $table->float('avg_queries')->default(0);
            $table->integer('n_plus_one_count')->default(0);

            // Phase 3 health score, computed during rollup.
            $table->float('health_score')->nullable();
            $table->string('health_grade', 1)->nullable();

            $table->unique(['date', 'uri', 'method']);
            $table->index(['uri', 'date']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('apa_daily_endpoint_stats');
    }
};
