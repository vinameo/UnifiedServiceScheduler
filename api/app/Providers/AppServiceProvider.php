<?php

namespace App\Providers;

use App\Domain\Auth\Role;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\MasterData\DealershipAccess;
use App\Domain\MasterData\Exceptions\TechnicianNotFound;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\Technician;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Support\Cache\DatabaseRateLimiter;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        Gate::define('admin', function (User $user): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            return $role === Role::ADMIN;
        });

        Gate::define('create-appointment', function (User $user): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            return $role === Role::SERVICE_ADVISOR || $role === Role::CUSTOMER;
        });

        Gate::define('cancel-appointment', function (User $user): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            return $role === Role::SERVICE_ADVISOR || $role === Role::CUSTOMER;
        });

        Gate::define('reschedule-appointment', function (User $user, ?Appointment $appointment = null): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            if ($role === Role::SERVICE_ADVISOR) {
                return $appointment === null || ($user->dealership_id !== null && $appointment->dealership_id === $user->dealership_id);
            }

            if ($role === Role::CUSTOMER) {
                if ($appointment === null) {
                    return true;
                }
                $customerId = $user->customer->id ?? $user->customer()->value('id');

                return $customerId !== null && $appointment->customer_id === $customerId;
            }

            return false;
        });

        Gate::define('confirm-hold', function (User $user, ?Appointment $appointment = null): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            if ($role !== Role::CUSTOMER) {
                return false;
            }

            if ($appointment === null) {
                return true;
            }

            $customerId = $user->customer->id ?? $user->customer()->value('id');

            return $customerId !== null && $appointment->customer_id === $customerId;
        });

        Gate::define('search-customers', function (User $user): bool {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

            return $role === Role::SERVICE_ADVISOR;
        });

        Gate::policy(Appointment::class, AppointmentPolicy::class);

        Route::bind('dealership', function (string $value): Dealership {
            /** @var User|null $user */
            $user = request()->user();

            return app(DealershipAccess::class)->resolveFor($user, $value);
        });

        Route::bind('appointment', function (string $value): Appointment {
            /** @var User|null $user */
            $user = request()->user() ?? auth()->user();
            if (! $user instanceof User) {
                throw new AppointmentNotFound;
            }

            return app(AppointmentRepositoryInterface::class)->findVisibleTo($user, $value);
        });

        Route::bind('technician', function (string $value, RoutingRoute $route): Technician {
            if (! Str::isUuid($value)) {
                throw new TechnicianNotFound;
            }

            $dealership = $route->parameter('dealership');
            $dealershipId = $dealership instanceof Dealership ? $dealership->id : (is_string($dealership) ? $dealership : '');

            $query = Technician::where('id', $value);
            if ($dealershipId !== '') {
                $query->where('dealership_id', $dealershipId);
            }

            $technician = $query->first();
            if ($technician === null) {
                throw new TechnicianNotFound;
            }

            return $technician;
        });
    }
}
