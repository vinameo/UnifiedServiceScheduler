<?php

namespace App\Providers;

use App\Domain\Auth\Repositories\UserRepository;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Services\SignInService;
use App\Domain\Auth\Services\SignInServiceInterface;
use App\Domain\Auth\Services\UserService;
use App\Domain\Auth\Services\UserServiceInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository and service interface to its class.
 * Add one line per pair when a feature adds a new repository or service.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        // Auth (F00)
        UserRepositoryInterface::class => UserRepository::class,
        UserServiceInterface::class => UserService::class,
        SignInServiceInterface::class => SignInService::class,
    ];
}
