<?php

namespace ApiPerformanceAnalyzer\Tests\Feature;

use ApiPerformanceAnalyzer\Models\Query;
use ApiPerformanceAnalyzer\Models\RequestProfile;
use ApiPerformanceAnalyzer\Storage\BatchStore;
use ApiPerformanceAnalyzer\Tests\TestCase;
use Illuminate\Support\Str;

class BatchStoreTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // Use the array cache as the buffer (non-redis fallback path).
        $app['config']->set('apa.storage.driver', 'batch');
        $app['config']->set('apa.storage.batch.buffer', 'array');
        $app['config']->set('apa.storage.batch.flush_at_size', 1000); // disable size-trigger
        $app['config']->set('cache.default', 'array');
    }

    public function test_buffer_holds_then_flush_bulk_inserts_with_child_fks(): void
    {
        /** @var BatchStore $store */
        $store = $this->app->make(BatchStore::class);

        $profile = [
            'uuid' => (string) Str::uuid(), 'method' => 'GET', 'route_name' => null,
            'uri' => 'api/items', 'raw_uri' => '/api/items', 'status_code' => 200,
            'duration_ms' => 120, 'db_query_count' => 2, 'db_time_ms' => 5,
            'peak_memory_kb' => 1024, 'is_slow' => false, 'sampled' => true,
            'has_n_plus_one' => false, 'external_call_count' => 0, 'external_time_ms' => 0,
            'user_id' => null, 'ip' => null, 'created_at' => now(),
        ];
        $children = ['queries' => [
            ['sql_hash' => 'h1', 'sql' => 'select 1', 'bindings_count' => 0, 'time_ms' => 1, 'connection' => 'testing', 'is_slow' => false],
            ['sql_hash' => 'h2', 'sql' => 'select 2', 'bindings_count' => 0, 'time_ms' => 2, 'connection' => 'testing', 'is_slow' => false],
        ], 'http_calls' => []];

        $store->store($profile, $children);

        // Nothing written until flush.
        $this->assertSame(0, RequestProfile::query()->count());

        $flushed = $store->flush();

        $this->assertSame(1, $flushed);
        $this->assertSame(1, RequestProfile::query()->count());

        $saved = RequestProfile::query()->first();
        $this->assertSame(2, Query::query()->where('profile_id', $saved->id)->count());
    }
}
