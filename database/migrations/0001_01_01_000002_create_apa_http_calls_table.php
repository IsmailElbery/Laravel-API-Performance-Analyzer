<?php

use ApiPerformanceAnalyzer\Support\Migrations\ApaMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends ApaMigration
{
    public function up(): void
    {
        $this->schema()->create('apa_http_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id')->index();
            $table->string('host')->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->string('url', 2048)->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->float('duration_ms')->default(0);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('apa_http_calls');
    }
};
