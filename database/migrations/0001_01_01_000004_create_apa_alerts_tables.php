<?php

use ApiPerformanceAnalyzer\Support\Migrations\ApaMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends ApaMigration
{
    public function up(): void
    {
        $this->schema()->create('apa_alert_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('uri')->nullable();          // null = applies to all endpoints
            $table->string('metric');                    // p95_ms | error_rate | n_plus_one_count | query_count
            $table->string('operator', 4)->default('>'); // > | >= | < | <=
            $table->float('threshold');
            $table->integer('window_minutes')->default(60);
            $table->integer('cooldown_minutes')->default(60);
            $table->json('channels')->nullable();        // overrides config channels
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('apa_alert_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rule_id')->index();
            $table->string('uri')->nullable();
            $table->string('metric');
            $table->float('observed_value');
            $table->float('threshold');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->index();
        });

        $this->schema()->create('apa_index_recommendations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sql_hash', 40)->index();
            $table->text('sample_sql');
            $table->integer('frequency')->default(0);
            $table->float('avg_time_ms')->default(0);
            $table->json('endpoints')->nullable();        // triggering endpoints
            $table->text('recommendation')->nullable();   // advisory only
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('apa_index_recommendations');
        $this->schema()->dropIfExists('apa_alert_events');
        $this->schema()->dropIfExists('apa_alert_rules');
    }
};
