<?php

namespace App\Providers;

use App\Domain\Auth\Role;
use App\Models\User;
use App\Support\Cache\DatabaseRateLimiter;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new DatabaseRateLimiter($app->make('cache')->driver(
                config('cache.limiter')
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $databaseLimiter = new DatabaseRateLimiter($this->app->make('cache')->driver(
            config('cache.limiter')
        ));
        $this->app->instance(RateLimiter::class, $databaseLimiter);
        \Illuminate\Support\Facades\RateLimiter::swap($databaseLimiter);

        JsonResource::withoutWrapping();

        Gate::define('customer', function (User $user): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            return $role === Role::CUSTOMER;
        });

        Gate::define('staff', function (User $user): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            return $role?->isStaff() ?? false;
        });
    }
}
