<?php

use ApiPerformanceAnalyzer\Support\Migrations\ApaMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends ApaMigration
{
    public function up(): void
    {
        $this->schema()->create('apa_queries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id');
            $table->string('sql_hash', 40)->index();   // sha1 — joins cross-request N+1 / slow-query views
            $table->text('sql');                        // truncated
            $table->integer('bindings_count')->default(0);
            $table->json('bindings')->nullable();       // only when privacy.store_bindings = true
            $table->float('time_ms')->default(0);
            $table->string('connection')->nullable();
            $table->boolean('is_slow')->default(false);

            $table->index('profile_id');
            $table->index(['sql_hash', 'is_slow']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('apa_queries');
    }
};
