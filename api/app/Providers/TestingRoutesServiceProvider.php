<?php

namespace App\Providers;

use App\Console\Commands\TestingRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TestingRoutesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestingRequest::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        $testingRoutes = base_path('routes/testing.php');
        if (file_exists($testingRoutes)) {
            Route::prefix('api/v1/_testing')
                ->middleware(['api'])
                ->group($testingRoutes);
        }
    }
}
