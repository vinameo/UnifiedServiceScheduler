<?php

namespace App\Domain\Workshop\Services;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Services\AppointmentTransitionInterface;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

class WorkshopService implements WorkshopServiceInterface
{
    public function __construct(
        private AppointmentTransitionInterface $transitionService,
    ) {}

    public function checkIn(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::CHECKED_IN,
            changes: [
                'checked_in_at' => $now,
            ],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }

    public function start(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::IN_PROGRESS,
            changes: [
                'started_at' => $now,
            ],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }

    public function complete(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::COMPLETED,
            changes: [
                'completed_at' => $now,
            ],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }
}
