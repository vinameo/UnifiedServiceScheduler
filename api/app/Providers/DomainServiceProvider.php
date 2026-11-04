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
use App\Domain\Auth\Services\CustomerService;
use App\Domain\Auth\Services\CustomerServiceInterface;
use App\Domain\Auth\Services\SignInService;
use App\Domain\Auth\Services\SignInServiceInterface;
use App\Domain\Auth\Services\SignUpService;
use App\Domain\Auth\Services\SignUpServiceInterface;
use App\Domain\Auth\Services\UserService;
use App\Domain\Auth\Services\UserServiceInterface;
use App\Domain\Booking\Repositories\AllocationSnapshotRepository;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepository;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\ResourceAllocationRepository;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\Repositories\VehicleRepository;
use App\Domain\Booking\Repositories\VehicleRepositoryInterface;
use App\Domain\Booking\Services\AppointmentEventRecorder;
use App\Domain\Booking\Services\AppointmentEventRecorderInterface;
use App\Domain\Booking\Services\AppointmentQueryService;
use App\Domain\Booking\Services\AppointmentQueryServiceInterface;
use App\Domain\Booking\Services\AppointmentTransition;
use App\Domain\Booking\Services\AppointmentTransitionInterface;
use App\Domain\Booking\Services\AppointmentWriter;
use App\Domain\Booking\Services\AppointmentWriterInterface;
use App\Domain\Booking\Services\AvailabilityCalculator;
use App\Domain\Booking\Services\AvailabilityCalculatorInterface;
use App\Domain\Booking\Services\AvailabilityService;
use App\Domain\Booking\Services\AvailabilityServiceInterface;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\BookingServiceInterface;
use App\Domain\Booking\Services\ExpiredHoldReleaser;
use App\Domain\Booking\Services\ExpiredHoldReleaserInterface;
use App\Domain\MasterData\Repositories\MasterDataRepository;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\MasterData\Services\MasterDataService;
use App\Domain\MasterData\Services\MasterDataServiceInterface;
use App\Domain\MasterData\Services\TechnicianService;
use App\Domain\MasterData\Services\TechnicianServiceInterface;
use App\Domain\MasterData\Services\UpcomingAssignmentsReader;
use App\Domain\Monitoring\Repositories\ApiEventRepository;
use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use App\Domain\Monitoring\Repositories\MetricCounterRepository;
use App\Domain\Monitoring\Repositories\MetricCounterRepositoryInterface;
use App\Domain\Monitoring\Services\ApiEventService;
use App\Domain\Monitoring\Services\ApiEventServiceInterface;
use App\Domain\Monitoring\Services\MetricCounterService;
use App\Domain\Monitoring\Services\MetricCounterServiceInterface;
use App\Domain\Monitoring\Services\MonitoringService;
use App\Domain\Monitoring\Services\MonitoringServiceInterface;
use App\Domain\Monitoring\Services\RequestMetrics;
use App\Domain\Notifications\Channels\ChannelRegistry;
use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\RecipientResolver;
use App\Domain\Notifications\RecipientResolverInterface;
use App\Domain\Notifications\Repositories\OutboxMessageRepository;
use App\Domain\Notifications\Repositories\OutboxMessageRepositoryInterface;
use App\Domain\Notifications\Services\OutboxRelay;
use App\Domain\Notifications\Services\OutboxRelayInterface;
use App\Domain\Notifications\Services\OutboxService;
use App\Domain\Notifications\Services\OutboxServiceInterface;
use App\Domain\Notifications\Services\OutboxWriter;
use App\Domain\Notifications\Services\OutboxWriterInterface;
use App\Domain\Notifications\Services\ReminderQueue;
use App\Domain\Notifications\Services\ReminderQueueInterface;
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
        CustomerServiceInterface::class => CustomerService::class,
        UserServiceInterface::class => UserService::class,
        SignInServiceInterface::class => SignInService::class,
        SignUpServiceInterface::class => SignUpService::class,

        // MasterData (F01)
        MasterDataRepositoryInterface::class => MasterDataRepository::class,
        MasterDataServiceInterface::class => MasterDataService::class,
        TechnicianServiceInterface::class => TechnicianService::class,
        UpcomingAssignmentsReader::class => ResourceAllocationRepository::class,

        // Allocation (F03)
        AllocationStrategy::class => LowestLevelFirstStrategy::class,
        CandidateFinderInterface::class => CandidateFinder::class,
        AllocationSnapshotRepositoryInterface::class => AllocationSnapshotRepository::class,
        BusyIntervalReader::class => ResourceAllocationRepository::class,

        // Booking (F04)
        ResourceAllocationRepositoryInterface::class => ResourceAllocationRepository::class,
        AppointmentRepositoryInterface::class => AppointmentRepository::class,
        VehicleRepositoryInterface::class => VehicleRepository::class,
        AppointmentWriterInterface::class => AppointmentWriter::class,
        AppointmentEventRecorderInterface::class => AppointmentEventRecorder::class,
        AppointmentTransitionInterface::class => AppointmentTransition::class,
        BookingServiceInterface::class => BookingService::class,
        AppointmentQueryServiceInterface::class => AppointmentQueryService::class,
        AvailabilityCalculatorInterface::class => AvailabilityCalculator::class,
        AvailabilityServiceInterface::class => AvailabilityService::class,
        ExpiredHoldReleaserInterface::class => ExpiredHoldReleaser::class,

        // Notifications (F04, F11)
        OutboxWriterInterface::class => OutboxWriter::class,
        RecipientResolverInterface::class => RecipientResolver::class,
        OutboxMessageRepositoryInterface::class => OutboxMessageRepository::class,
        OutboxRelayInterface::class => OutboxRelay::class,
        ReminderQueueInterface::class => ReminderQueue::class,
        OutboxServiceInterface::class => OutboxService::class,

        // Monitoring (F08)
        MetricCounterRepositoryInterface::class => MetricCounterRepository::class,
        MetricCounterServiceInterface::class => MetricCounterService::class,
        ApiEventRepositoryInterface::class => ApiEventRepository::class,
        ApiEventServiceInterface::class => ApiEventService::class,
        MonitoringServiceInterface::class => MonitoringService::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        ChannelRegistryInterface::class => ChannelRegistry::class,
    ];

    public function register(): void
    {
        $this->app->scoped(RequestMetrics::class);
    }
}
