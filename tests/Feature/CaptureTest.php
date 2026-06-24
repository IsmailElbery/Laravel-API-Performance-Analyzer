<?php

namespace ApiPerformanceAnalyzer\Tests\Feature;

use ApiPerformanceAnalyzer\Models\RequestProfile;
use ApiPerformanceAnalyzer\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class CaptureTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        // Use the package's registered `apa` alias directly. The provider also
        // auto-attaches to the real `api` group (asserted in WiringTest), but
        // Testbench rebuilds that group per request, so we apply the alias here
        // to exercise the full capture pipeline.
        $router->middleware('apa')->group(function ($router) {
            $router->get('/api/ping', fn () => ['ok' => true]);

            $router->get('/api/users/{id}', function ($id) {
                // Trigger a couple of queries to exercise the QueryCollector.
                DB::select('select 1 as n');
                DB::select('select 2 as n');

                return ['id' => $id];
            });

            $router->get('/api/boom', function () {
                abort(500, 'kaboom');
            });
        });
    }

    public function test_it_captures_a_request_profile(): void
    {
        $this->getJson('/api/users/42')->assertOk();

        $profile = RequestProfile::query()->first();

        $this->assertNotNull($profile);
        $this->assertSame('GET', $profile->method);
        $this->assertSame('api/users/{id}', $profile->uri);   // normalized via route pattern
        $this->assertSame(200, $profile->status_code);
        $this->assertGreaterThanOrEqual(2, $profile->db_query_count);
        $this->assertTrue($profile->sampled);
    }

    public function test_it_stores_child_queries_for_retained_profile(): void
    {
        $this->getJson('/api/users/7')->assertOk();

        $profile = RequestProfile::query()->with('queries')->first();

        $this->assertNotNull($profile);
        $this->assertGreaterThanOrEqual(2, $profile->queries->count());
    }

    public function test_it_always_captures_errors(): void
    {
        // Even with sampling off, a 5xx must be retained.
        config()->set('apa.sample_rate', 0.0);

        $this->getJson('/api/boom')->assertStatus(500);

        $profile = RequestProfile::query()->where('status_code', 500)->first();
        $this->assertNotNull($profile);
        $this->assertFalse($profile->sampled);
    }

    public function test_it_does_not_profile_excluded_paths(): void
    {
        config()->set('apa.except_paths', ['api/ping']);

        $this->getJson('/api/ping')->assertOk();

        $this->assertSame(0, RequestProfile::query()->count());
    }
}
