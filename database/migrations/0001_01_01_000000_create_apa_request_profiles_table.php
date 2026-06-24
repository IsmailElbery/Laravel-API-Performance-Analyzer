<?php

use ApiPerformanceAnalyzer\Support\Migrations\ApaMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends ApaMigration
{
    public function up(): void
    {
        $this->schema()->create('apa_request_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('method', 10);
            $table->string('route_name')->nullable();
            $table->string('uri')->index();          // normalized pattern, e.g. users/{id}
            $table->string('raw_uri', 2048);
            $table->smallInteger('status_code')->nullable();
            $table->float('duration_ms');
            $table->integer('db_query_count')->default(0);
            $table->float('db_time_ms')->default(0);
            $table->integer('peak_memory_kb')->default(0);
            $table->boolean('is_slow')->default(false);
            $table->boolean('sampled')->default(false);

            // Phase 2 flags (kept on the parent so aggregate views are cheap).
            $table->boolean('has_n_plus_one')->default(false);
            $table->integer('external_call_count')->default(0);
            $table->float('external_time_ms')->default(0);

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 64)->nullable();     // hashed (fixed-width) by default
            $table->timestamp('created_at')->index();

            $table->index(['uri', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index(['is_slow', 'created_at']);
            $table->index(['duration_ms']);
            $table->index(['has_n_plus_one', 'created_at']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('apa_request_profiles');
    }
};
