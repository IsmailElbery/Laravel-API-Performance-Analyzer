<?php

namespace ApiPerformanceAnalyzer\Tests\Feature;

use ApiPerformanceAnalyzer\Http\Middleware\ApaMiddleware;
use ApiPerformanceAnalyzer\Tests\TestCase;

class WiringTest extends TestCase
{
    public function test_middleware_is_auto_attached_to_the_api_group(): void
    {
        // The provider pushes ApaMiddleware onto the `api` group at boot. (In a
        // real Laravel 11 app this persists to dispatch; Testbench rebuilds the
        // group per request, hence the capture tests use the alias.)
        $group = $this->app['router']->getMiddlewareGroups()['api'] ?? [];

        $this->assertContains(ApaMiddleware::class, $group);
    }

    public function test_apa_alias_is_registered(): void
    {
        $this->assertArrayHasKey('apa', $this->app['router']->getMiddleware());
    }

    public function test_storage_driver_resolves_from_config(): void
    {
        $this->assertInstanceOf(
            \ApiPerformanceAnalyzer\Storage\SyncStore::class,
            $this->app->make(\ApiPerformanceAnalyzer\Contracts\ProfileStore::class)
        );
    }
}
