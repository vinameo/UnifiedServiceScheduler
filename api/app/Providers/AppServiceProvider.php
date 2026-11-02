<?php

namespace App\Providers;

use App\Domain\Auth\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
