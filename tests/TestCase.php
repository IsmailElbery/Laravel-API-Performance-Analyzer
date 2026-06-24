<?php

namespace ApiPerformanceAnalyzer\Tests;

use ApiPerformanceAnalyzer\ApaServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [ApaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Inline writes so assertions are synchronous; profiler shares the
        // default connection in tests.
        $config->set('apa.storage.driver', 'sync');
        $config->set('apa.storage.connection', null);
        $config->set('apa.sample_rate', 1.0);
        $config->set('apa.api.middleware', ['api']);       // drop auth for tests
        $config->set('apa.dashboard.middleware', ['web']);
        $config->set('apa.dashboard.gate', 'apaTestGate');
        \Illuminate\Support\Facades\Gate::define('apaTestGate', fn () => true);

        // Real Laravel 11 apps ship an "api" middleware group; Testbench does
        // not, so define it here to exercise the provider's auto-attach path.
        $app['router']->middlewareGroup('api', []);
    }
}
