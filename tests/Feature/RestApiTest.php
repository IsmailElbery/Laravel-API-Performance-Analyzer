<?php

namespace ApiPerformanceAnalyzer\Tests\Feature;

use ApiPerformanceAnalyzer\Models\RequestProfile;
use ApiPerformanceAnalyzer\Tests\TestCase;

class RestApiTest extends TestCase
{
    protected function seedProfiles(): void
    {
        foreach (range(1, 30) as $i) {
            RequestProfile::query()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'method' => 'GET',
                'route_name' => null,
                'uri' => 'api/users/{id}',
                'raw_uri' => '/api/users/'.$i,
                'status_code' => $i % 10 === 0 ? 500 : 200,
                'duration_ms' => 100 + $i,
                'db_query_count' => 3,
                'db_time_ms' => 10,
                'peak_memory_kb' => 2048,
                'is_slow' => $i > 25,
                'sampled' => true,
                'has_n_plus_one' => $i % 7 === 0,
                'created_at' => now()->subMinutes($i),
            ]);
        }
    }

    public function test_requests_index_returns_paginated_list(): void
    {
        $this->seedProfiles();

        $this->getJson('/apa/api/requests?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(10, 'data');
    }

    public function test_single_request_lookup_by_uuid(): void
    {
        $this->seedProfiles();
        $uuid = RequestProfile::query()->first()->uuid;

        $this->getJson("/apa/api/requests/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);
    }

    public function test_endpoints_aggregate_returns_p95_and_error_rate(): void
    {
        $this->seedProfiles();

        $response = $this->getJson('/apa/api/endpoints')->assertOk();

        $first = $response->json('data.0');
        $this->assertSame('api/users/{id}', $first['uri']);
        $this->assertArrayHasKey('p95_ms', $first);
        $this->assertArrayHasKey('error_rate', $first);
        $this->assertGreaterThan(0, $first['count']);
    }

    public function test_overview_stats(): void
    {
        $this->seedProfiles();

        $this->getJson('/apa/api/stats/overview')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 30);
    }
}
