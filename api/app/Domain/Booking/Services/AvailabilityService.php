<?php

namespace App\Domain\Booking\Services;

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
        private AvailabilityCalculator $calculator,
        private MasterDataRepositoryInterface $masterDataRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function forDay(
        string $dealershipId,
        ServiceType|string $type,
        string $localDate,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
        ?User $actor = null,
    ): DayAvailability {
        if (is_string($type)) {
            $serviceType = $this->masterDataRepository->serviceTypeFor($dealershipId, $type);
            if ($serviceType === null || ! $serviceType->active) {
                throw new ServiceTypeNotFound;
            }
        } else {
            $serviceType = $type;
            if (! $serviceType->active || $serviceType->dealership_id !== $dealershipId) {
                throw new ServiceTypeNotFound;
            }
        }

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
}
