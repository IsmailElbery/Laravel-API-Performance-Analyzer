<?php

namespace ApiPerformanceAnalyzer;

use ApiPerformanceAnalyzer\Analysis\HealthScorer;
use ApiPerformanceAnalyzer\Console\AlertsCommand;
use ApiPerformanceAnalyzer\Console\ExplainCommand;
use ApiPerformanceAnalyzer\Console\FlushCommand;
use ApiPerformanceAnalyzer\Console\PruneCommand;
use ApiPerformanceAnalyzer\Console\ReportCommand;
use ApiPerformanceAnalyzer\Console\RollupCommand;
use ApiPerformanceAnalyzer\Contracts\ProfileStore;
use ApiPerformanceAnalyzer\Http\Middleware\ApaMiddleware;
use ApiPerformanceAnalyzer\Storage\BatchStore;
use ApiPerformanceAnalyzer\Storage\QueueStore;
use ApiPerformanceAnalyzer\Storage\SyncStore;
use ApiPerformanceAnalyzer\Support\CollectorRegistry;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ApaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/apa.php', 'apa');

        // Per-request accumulator. scoped() => reset between requests under
        // Octane when reset_context_per_request is on (handled in boot()).
        $this->app->scoped(ProfileContext::class, fn () => new ProfileContext);

        $this->app->singleton(CollectorRegistry::class);

        // Storage driver resolution.
        $this->app->singleton(ProfileStore::class, function ($app) {
            return match ($app['config']->get('apa.storage.driver', 'queue')) {
                'sync' => $app->make(SyncStore::class),
                'batch' => $app->make(BatchStore::class),
                default => $app->make(QueueStore::class),
            };
        });

        $this->app->singleton(HealthScorer::class, function ($app) {
            $cfg = $app['config']->get('apa.health');

            return new HealthScorer(
                (int) ($cfg['min_samples'] ?? 50),
                (array) ($cfg['weights'] ?? []),
                (array) ($cfg['norm'] ?? []),
            );
        });

        $this->app->singleton(ApaMiddleware::class);
    }

    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootListeners();
        $this->bootMiddleware();
        $this->bootGate();
        $this->bootRoutes();
        $this->bootViews();
        $this->bootCommands();
        $this->bootOctane();
    }

    protected function bootPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/apa.php' => config_path('apa.php')], 'apa-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'apa-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/apa')], 'apa-views');
    }

    /** Load migrations from the package so a bare `migrate` works without publishing. */
    protected function bootListeners(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! (bool) $this->app['config']->get('apa.enabled', true)) {
            return;
        }

        // Attach collector process-level listeners (DB::listen, HTTP middleware).
        $this->app->make(CollectorRegistry::class)->bootListeners();
    }

    protected function bootMiddleware(): void
    {
        if (! (bool) $this->app['config']->get('apa.enabled', true)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('apa', ApaMiddleware::class);

        // Auto-attach to the `api` group so installation is "add nothing".
        $this->app->booted(function () use ($router) {
            if ($router->hasMiddlewareGroup('api')) {
                $router->pushMiddlewareToGroup('api', ApaMiddleware::class);
            }
        });
    }

    protected function bootGate(): void
    {
        $ability = $this->app['config']->get('apa.dashboard.gate', 'viewApa');

        if (! Gate::has($ability)) {
            // Default: open in local, closed elsewhere unless the app defines it.
            Gate::define($ability, fn ($user = null) => $this->app->environment('local'));
        }
    }

    protected function bootRoutes(): void
    {
        $config = $this->app['config'];

        if ((bool) $config->get('apa.dashboard.enabled', true)) {
            Route::group([
                'prefix' => $config->get('apa.dashboard.path', 'apa'),
                'middleware' => $config->get('apa.dashboard.middleware', ['web']),
                'as' => 'apa.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        if ((bool) $config->get('apa.api.enabled', true)) {
            Route::group([
                'prefix' => $config->get('apa.api.path', 'apa/api'),
                'middleware' => $config->get('apa.api.middleware', ['api']),
                'as' => 'apa.api.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }
    }

    protected function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'apa');
    }

    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneCommand::class,
                RollupCommand::class,
                FlushCommand::class,
                AlertsCommand::class,
                ExplainCommand::class,
                ReportCommand::class,
            ]);
        }
    }

    /**
     * Octane safety: reset/rebind the per-request ProfileContext on every request
     * so no state leaks between requests sharing a worker process.
     */
    protected function bootOctane(): void
    {
        if (! (bool) $this->app['config']->get('apa.octane.reset_context_per_request', true)) {
            return;
        }

        if (! class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            return;
        }

        $events = $this->app['events'];
        $events->listen(\Laravel\Octane\Events\RequestReceived::class, function ($event) {
            $event->sandbox->forgetScopedInstances();
        });
    }
}
