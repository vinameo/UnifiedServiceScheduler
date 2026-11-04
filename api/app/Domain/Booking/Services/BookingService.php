<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\OpeningHours;
use App\Domain\Allocation\TimeRange;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Role;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\BookingTimeRules;
use App\Domain\Booking\CancelReason;
use App\Domain\Booking\CreateBookingInput;
use App\Domain\Booking\CustomerChangePolicy;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\CustomerNotFound;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\Exceptions\TooLateToCancel;
use App\Domain\Booking\Exceptions\VehicleNotFound;
use App\Domain\Booking\Exceptions\VehicleNotOwnedByCustomer;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\VehicleRepositoryInterface;
use App\Domain\MasterData\Exceptions\DealershipNotFound;
use App\Domain\MasterData\Exceptions\ServiceTypeNotFound;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Tracing\Tracer;
use Carbon\CarbonImmutable;
use DateTimeZone;

final readonly class BookingService implements BookingServiceInterface
{
    private AppointmentRepositoryInterface $appointmentRepository;

    private AppointmentTransitionInterface $appointmentTransition;

    private Tracer $tracer;

    public function __construct(
        private MasterDataRepositoryInterface $masterDataRepository,
        private CustomerRepositoryInterface $customerRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private AllocationSnapshotRepositoryInterface $snapshotRepository,
        private CandidateFinderInterface $candidateFinder,
        private AppointmentWriterInterface $appointmentWriter,
        private ?AvailabilityServiceInterface $availabilityService = null,
        ?AppointmentRepositoryInterface $appointmentRepository = null,
        ?AppointmentTransitionInterface $appointmentTransition = null,
        ?Tracer $tracer = null,
    ) {
        $this->appointmentRepository = $appointmentRepository ?? app(AppointmentRepositoryInterface::class);
        $this->appointmentTransition = $appointmentTransition ?? app(AppointmentTransitionInterface::class);
        $this->tracer = $tracer ?? app(Tracer::class);
    }

    public function create(CreateBookingInput $input, User $actor): Appointment
    {
        $role = $actor->role instanceof Role ? $actor->role : Role::tryFrom((string) $actor->role);
        $isCustomer = $role === Role::CUSTOMER;

        [$dealership, $serviceType, $customer, $vehicle, $range, $now] = $this->tracer->span('booking.validate', function () use ($input, $actor, $isCustomer) {
            // 1. Dealership
            $dealershipId = $isCustomer ? $input->dealershipId : $actor->dealership_id;
            if ($dealershipId === null) {
                throw new DealershipNotFound;
            }

            $dealership = $this->masterDataRepository->findDealership($dealershipId);
            if ($dealership === null) {
                throw new DealershipNotFound;
            }

            // 2. Service type
            $serviceType = $this->masterDataRepository->serviceTypeFor($dealershipId, $input->serviceTypeId);
            if ($serviceType === null || ! $serviceType->active) {
                throw new ServiceTypeNotFound;
            }

            // 3. Customer
            if ($isCustomer) {
                $customer = $actor->customer;
                if ($customer === null) {
                    throw new CustomerNotFound;
                }
            } else {
                if ($input->customerId === null) {
                    throw new CustomerNotFound;
                }
                $customer = $this->customerRepository->find($input->customerId);
                if ($customer === null) {
                    throw new CustomerNotFound;
                }
            }

            // 4. Vehicle
            $vehicle = $this->vehicleRepository->find($input->vehicleId);
            if ($vehicle === null) {
                throw new VehicleNotFound;
            }

            if ($vehicle->customer_id !== $customer->id) {
                if ($isCustomer) {
                    throw new VehicleNotFound;
                }
                throw new VehicleNotOwnedByCustomer;
            }

            // 5. BookingTimeRules
            $now = CarbonImmutable::now();
            $openingHoursList = $this->masterDataRepository->openingHours($dealershipId);
            $openingHoursMap = [];
            foreach ($openingHoursList as $oh) {
                $openingHoursMap[$oh->weekday] = [
                    'opens_at' => $oh->opens_at,
                    'closes_at' => $oh->closes_at,
                ];
            }

            $openingHours = OpeningHours::fromWeekdayMap($openingHoursMap, $dealership->timezone);
            $range = BookingTimeRules::check($input->desiredStart, $serviceType->duration_minutes, $openingHours, $now);

            return [$dealership, $serviceType, $customer, $vehicle, $range, $now];
        });

        // 6. Candidate finder
        $dealershipId = $dealership->id;
        $tz = new DateTimeZone($dealership->timezone);
        $localDate = $range->start->setTimezone($tz)->toDateString();
        $snapshot = $this->snapshotRepository->forDay($dealershipId, $localDate, $now);

        $allocationRequest = new AllocationRequest(
            dealershipId: $dealershipId,
            requiredLevel: $serviceType->requiredLevel(),
            requiredBayType: $serviceType->required_bay_type,
            range: $range,
        );

        $result = $this->tracer->span('allocation.find_candidates', function () use ($allocationRequest, $snapshot) {
            return $this->candidateFinder->find($allocationRequest, $snapshot);
        });

        try {
            if (empty($result->pairs)) {
                throw new NoAvailability(
                    reason: $result->failure !== null ? $result->failure->value : 'SLOT_TAKEN',
                    requestedStart: $range->start->setTimezone($tz),
                    requestedEnd: $range->end->setTimezone($tz),
                    dealershipId: $dealershipId,
                );
            }

            // 7. Write appointment
            $source = $isCustomer ? 'CUSTOMER' : 'STAFF';
            $newAppointment = new NewAppointment(
                dealershipId: $dealershipId,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                serviceTypeId: $serviceType->id,
                range: $range,
                status: AppointmentStatus::CONFIRMED,
                expiresAt: null,
                reminderOptIn: $input->reminderOptIn,
                source: $source,
                createdByUserId: $actor->id,
            );

            return $this->tracer->span('booking.write', function () use ($newAppointment, $result, $now) {
                return $this->appointmentWriter->create($newAppointment, $result->pairs, $now);
            });
        } catch (NoAvailability $e) {
            $availability = $this->availabilityService ?? app(AvailabilityServiceInterface::class);
            $suggestions = $availability->near(
                dealershipId: $dealershipId,
                type: $serviceType,
                wanted: $range->start->setTimezone($tz),
                now: $now,
                actor: $actor,
            );

            $formattedAlternatives = array_map(fn (TimeRange $r) => [
                'start' => $r->start->setTimezone($tz)->toIso8601String(),
                'end' => $r->end->setTimezone($tz)->toIso8601String(),
            ], $suggestions);

            throw new NoAvailability(
                reason: $e->reason,
                requestedStart: $e->requestedStart->setTimezone($tz),
                requestedEnd: $e->requestedEnd->setTimezone($tz),
                dealershipId: $e->dealershipId,
                detail: $e->detail,
                alternatives: $formattedAlternatives,
            );
        }
    }

    public function cancel(
        User $actor,
        string $appointmentId,
        int $expectedVersion,
        ?CancelReason $reason,
        ?string $note,
        CarbonImmutable $now,
    ): Appointment {
        $appointment = $this->appointmentRepository->findVisibleTo($actor, $appointmentId);

        // D-07.4: Cancelling a CANCELLED booking returns 200 with current state, nothing written
        if ($appointment->status === AppointmentStatus::CANCELLED) {
            return $appointment;
        }

        $role = $actor->role instanceof Role ? $actor->role : Role::tryFrom((string) $actor->role);
        $isCustomer = $role === Role::CUSTOMER;

        // Customer time check: CONFIRMED requires start - now >= 2 hours (INV-07.3, BR-07.2)
        if ($isCustomer && $appointment->status === AppointmentStatus::CONFIRMED) {
            if (! CustomerChangePolicy::isWithinCustomerChangeWindow($appointment, $now)) {
                $dealership = $appointment->dealership ?? $this->masterDataRepository->findDealership($appointment->dealership_id);
                $phone = $dealership !== null ? $dealership->phone : '';
                throw new TooLateToCancel(
                    dealershipPhone: $phone,
                    start: CarbonImmutable::parse($appointment->start_at)->toIso8601String(),
                );
            }
        }

        $changes = [
            'cancel_reason' => $reason?->value,
            'cancel_note' => $note !== null ? trim($note) : null,
            'cancelled_at' => $now,
            'cancelled_by_user_id' => $actor->id,
        ];

        $outbox = $appointment->status === AppointmentStatus::CONFIRMED
            ? OutboxEventType::AppointmentCancelled
            : null;

        try {
            return $this->appointmentTransition->transition(
                id: $appointment->id,
                expectedVersion: $expectedVersion,
                to: AppointmentStatus::CANCELLED,
                changes: $changes,
                outbox: $outbox,
                actor: $actor,
                now: $now,
            );
        } catch (AppointmentModified $e) {
            // D-07.5: if versioned update hit 0 rows or version mismatch, but fresh read is CANCELLED -> 200
            $fresh = $this->appointmentRepository->find($appointmentId);
            if ($fresh !== null && $fresh->status === AppointmentStatus::CANCELLED) {
                return $fresh;
            }
            throw $e;
        }
    }
}
