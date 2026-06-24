<?php

namespace ApiPerformanceAnalyzer\Tests\Feature;

use ApiPerformanceAnalyzer\Models\DailyEndpointStat;
use ApiPerformanceAnalyzer\Models\RequestProfile;
use ApiPerformanceAnalyzer\Tests\TestCase;
use Illuminate\Support\Str;

class RollupAndCommandsTest extends TestCase
{
    protected function seedYesterday(int $n = 60): void
    {
        foreach (range(1, $n) as $i) {
            RequestProfile::query()->create([
                'uuid' => (string) Str::uuid(),
                'method' => 'GET',
                'uri' => 'api/orders',
                'raw_uri' => '/api/orders',
                'status_code' => $i % 20 === 0 ? 500 : 200,
                'duration_ms' => 80 + $i,
                'db_query_count' => 4,
                'db_time_ms' => 12,
                'peak_memory_kb' => 1024,
                'is_slow' => false,
                'sampled' => true,
                'created_at' => now()->subDay()->setTime(10, 0)->addMinutes($i),
            ]);
        }
    }

    public function test_rollup_writes_daily_stats_with_health_score(): void
    {
        $this->seedYesterday();

        $this->artisan('apa:rollup')->assertSuccessful();

        $stat = DailyEndpointStat::query()->where('uri', 'api/orders')->first();

        $this->assertNotNull($stat);
        $this->assertSame(60, $stat->count);
        $this->assertGreaterThan(0, $stat->p95_ms);
        // 60 samples >= min_samples (50) so a score/grade is assigned.
        $this->assertNotNull($stat->health_score);
        $this->assertNotNull($stat->health_grade);
    }

    public function test_prune_removes_old_profiles(): void
    {
        RequestProfile::query()->create([
            'uuid' => (string) Str::uuid(), 'method' => 'GET', 'uri' => 'api/old', 'raw_uri' => '/api/old',
            'status_code' => 200, 'duration_ms' => 10, 'created_at' => now()->subDays(30),
        ]);
        RequestProfile::query()->create([
            'uuid' => (string) Str::uuid(), 'method' => 'GET', 'uri' => 'api/new', 'raw_uri' => '/api/new',
            'status_code' => 200, 'duration_ms' => 10, 'created_at' => now(),
        ]);

        $this->artisan('apa:prune --days=14')->assertSuccessful();

        $this->assertSame(1, RequestProfile::query()->count());
        $this->assertNotNull(RequestProfile::query()->where('uri', 'api/new')->first());
    }
}
