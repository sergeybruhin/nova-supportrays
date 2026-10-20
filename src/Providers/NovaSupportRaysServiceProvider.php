<?php

namespace SergeyBruhin\NovaSupportRays\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Nova;
use SergeyBruhin\NovaSupportRays\Client\ManagerClient;
use SergeyBruhin\NovaSupportRays\Http\Middleware\Authorize;
use SergeyBruhin\NovaSupportRays\Support\Settings;

class NovaSupportRaysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/nova-supportrays.php', 'nova-supportrays');

        $this->app->bind(ManagerClient::class, fn (): ManagerClient => new ManagerClient(
            Settings::baseUrl(),
            (int) config('nova-supportrays.timeout', 10),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->app->booted(function (): void {
            $this->routes();
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/nova-supportrays.php' => config_path('nova-supportrays.php'),
            ], 'config');
        }
    }

    /**
     * Nova's own auth middleware plus the tool's gate. Registered on `booted`
     * so the Nova config is in place; skipped once routes are cached.
     */
    private function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // The pages, inside Nova's own prefix and domain.
        Nova::router(['nova', Authenticate::class, Authorize::class], 'supportrays')
            ->group(__DIR__.'/../../routes/inertia.php');

        // The JSON the pages talk to, under the convention Nova's front end expects.
        Route::middleware(['nova', Authenticate::class, Authorize::class])
            ->domain(config('nova.domain'))
            ->prefix('nova-vendor/supportrays')
            ->group(__DIR__.'/../../routes/api.php');
    }
}
