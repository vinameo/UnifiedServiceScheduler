<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Exceptions\InvalidStatusTransition;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use Carbon\CarbonImmutable;

class ExpiredHoldReleaser implements ExpiredHoldReleaserInterface
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentTransitionInterface $appointmentTransition,
    ) {}

    /**
     * INV-10.6. Returns the number of holds expired. Each in its own transaction, before the caller's write.
     */
    public function releaseBlocking(
        string $dealershipId,
        TimeRange $range,
        ?string $vehicleId,
        CarbonImmutable $now,
    ): int {
        $blockingHolds = $this->appointmentRepository->findExpiredHoldsBlocking(
            dealershipId: $dealershipId,
            range: $range,
            vehicleId: $vehicleId,
            now: $now,
        );

        $released = 0;
        foreach ($blockingHolds as $hold) {
            try {
                $this->appointmentTransition->transition(
                    id: $hold->id,
                    expectedVersion: $hold->version,
                    to: AppointmentStatus::EXPIRED,
                    changes: [],
                    outbox: null,
                    actor: null,
                    now: $now,
                );
                $released++;
            } catch (AppointmentModified|InvalidStatusTransition|AppointmentNotFound) {
                // Concurrently modified, confirmed, expired, or cancelled: skip
                continue;
            }
        }

        return $released;
    }
}
