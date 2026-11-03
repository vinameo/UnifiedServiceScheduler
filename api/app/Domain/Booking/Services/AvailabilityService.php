<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\DayAvailability;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\MasterData\Exceptions\ServiceTypeNotFound;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class AvailabilityService implements AvailabilityServiceInterface
{
    public function __construct(
        private AvailabilityCalculatorInterface $calculator,
        private MasterDataRepositoryInterface $masterDataRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private AlternativeTimesFinder $alternativeTimesFinder,
    ) {}

    public function forDay(
        string $dealershipId,
        ServiceType|string $type,
        string $localDate,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
        ?User $actor = null,
    ): DayAvailability {
        $serviceType = $this->resolveServiceType($dealershipId, $type);

        if ($excludeAppointmentId !== null && $actor !== null) {
            $this->appointmentRepository->findVisibleTo($actor, $excludeAppointmentId);
        }

        return $this->calculator->forDay(
            dealershipId: $dealershipId,
            type: $serviceType,
            localDate: $localDate,
            now: $now,
            excludeAppointmentId: $excludeAppointmentId,
        );
    }

    /**
     * @return list<TimeRange>
     */
    public function near(
        string $dealershipId,
        ServiceType|string $type,
        CarbonImmutable $wanted,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
        int $limit = 3,
        ?User $actor = null,
    ): array {
        $serviceType = $this->resolveServiceType($dealershipId, $type);

        if ($excludeAppointmentId !== null && $actor !== null) {
            $this->appointmentRepository->findVisibleTo($actor, $excludeAppointmentId);
        }

        return $this->alternativeTimesFinder->near(
            dealershipId: $dealershipId,
            type: $serviceType,
            wanted: $wanted,
            now: $now,
            excludeAppointmentId: $excludeAppointmentId,
            limit: $limit,
        );
    }

    private function resolveServiceType(string $dealershipId, ServiceType|string $type): ServiceType
    {
        if (is_string($type)) {
            $serviceType = $this->masterDataRepository->serviceTypeFor($dealershipId, $type);
            if ($serviceType === null || ! $serviceType->active) {
                throw new ServiceTypeNotFound;
            }

            return $serviceType;
        }

        if (! $type->active || $type->dealership_id !== $dealershipId) {
            throw new ServiceTypeNotFound;
        }

        return $type;
    }
}
