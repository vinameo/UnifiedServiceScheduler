<?php

namespace App\Domain\Workshop\Services;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Exceptions\InvalidStatusTransition;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Services\AppointmentTransitionInterface;
use App\Domain\Workshop\Exceptions\NeedsReschedule;
use App\Domain\Workshop\Exceptions\NotServiceDay;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

class WorkshopService implements WorkshopServiceInterface
{
    public function __construct(
        private AppointmentTransitionInterface $transitionService,
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function checkIn(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        $appointment = $this->appointmentRepository->find($appointmentId);
        if ($appointment === null) {
            throw new AppointmentNotFound($appointmentId);
        }

        // 412 beats 422: if version is stale, caller is looking at old data
        if ($appointment->version !== $expectedVersion) {
            AppointmentModifiedDetected::dispatch($appointmentId, $expectedVersion);
            throw new AppointmentModified($appointmentId, $appointment);
        }

        if (! $appointment->status->canMoveTo(AppointmentStatus::CHECKED_IN)) {
            throw new InvalidStatusTransition($appointment->status, AppointmentStatus::CHECKED_IN);
        }

        $tz = $appointment->dealership->timezone ?? 'UTC';
        $startAt = CarbonImmutable::parse($appointment->start_at);
        $appointmentDate = $startAt->setTimezone($tz)->toDateString();
        $currentDate = $now->setTimezone($tz)->toDateString();

        if ($appointmentDate !== $currentDate) {
            throw new NotServiceDay($appointmentDate, $currentDate, $tz);
        }

        if ($appointment->contact_required_reason !== null || ! $this->appointmentRepository->hasActiveAllocations($appointment->id)) {
            throw new NeedsReschedule;
        }

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
