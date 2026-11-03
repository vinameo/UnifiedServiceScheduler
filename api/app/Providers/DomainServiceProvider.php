<?php

namespace App\Providers;

use App\Domain\Allocation\AllocationStrategy;
use App\Domain\Allocation\CandidateFinder;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\Contracts\BusyIntervalReader;
use App\Domain\Allocation\LowestLevelFirstStrategy;
use App\Domain\Auth\Repositories\CustomerRepository;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepository;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Services\SignInService;
use App\Domain\Auth\Services\SignInServiceInterface;
use App\Domain\Auth\Services\SignUpService;
use App\Domain\Auth\Services\SignUpServiceInterface;
use App\Domain\Auth\Services\UserService;
use App\Domain\Auth\Services\UserServiceInterface;
use App\Domain\Booking\Repositories\AllocationSnapshotRepository;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\Repositories\NoBusyIntervals;
use App\Domain\MasterData\Repositories\MasterDataRepository;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\MasterData\Services\MasterDataService;
use App\Domain\MasterData\Services\MasterDataServiceInterface;
use App\Domain\MasterData\Services\NoUpcomingAssignments;
use App\Domain\MasterData\Services\TechnicianService;
use App\Domain\MasterData\Services\TechnicianServiceInterface;
use App\Domain\MasterData\Services\UpcomingAssignmentsReader;
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
        CustomerRepositoryInterface::class => CustomerRepository::class,
        UserServiceInterface::class => UserService::class,
        SignInServiceInterface::class => SignInService::class,
        SignUpServiceInterface::class => SignUpService::class,

        // MasterData (F01)
        MasterDataRepositoryInterface::class => MasterDataRepository::class,
        MasterDataServiceInterface::class => MasterDataService::class,
        TechnicianServiceInterface::class => TechnicianService::class,
        UpcomingAssignmentsReader::class => NoUpcomingAssignments::class,

        // Allocation (F03)
        AllocationStrategy::class => LowestLevelFirstStrategy::class,
        CandidateFinderInterface::class => CandidateFinder::class,
        AllocationSnapshotRepositoryInterface::class => AllocationSnapshotRepository::class,
        BusyIntervalReader::class => NoBusyIntervals::class,
    ];
}
