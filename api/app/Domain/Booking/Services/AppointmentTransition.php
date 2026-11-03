<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Exceptions\InvalidStatusTransition;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentTransition implements AppointmentTransitionInterface
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AppointmentEventRecorderInterface $eventRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function transition(
        string $id,
        int $expectedVersion,
        AppointmentStatus $to,
        array $changes,
        ?OutboxEventType $outbox,
        ?User $actor,
        CarbonImmutable $now,
    ): Appointment {
        $current = $this->appointmentRepository->find($id);
        if ($current === null) {
            throw new AppointmentNotFound($id);
        }

        // 412 beats 422: if version is stale, caller is looking at old data
        if ($current->version !== $expectedVersion) {
            AppointmentModifiedDetected::dispatch($id, $expectedVersion);
            throw new AppointmentModified($id, $current);
        }

        if (! $current->status->canMoveTo($to)) {
            throw new InvalidStatusTransition($current->status, $to);
        }

        return DB::transaction(function () use ($id, $expectedVersion, $to, $changes, $actor, $now, $current) {
            $updateData = array_merge($changes, [
                'status' => $to->value,
            ]);

            $newVersion = $this->appointmentRepository->updateVersioned($id, $expectedVersion, $updateData, $now);

            if (! $to->holdsSlot()) {
                $this->allocationRepository->deactivateFor($id, $now);
            }

            $eventType = match ($to) {
                AppointmentStatus::IN_PROGRESS => AppointmentEventType::STARTED,
                default => AppointmentEventType::from($to->value),
            };

            $actorRole = null;
            if ($actor !== null) {
                $actorRole = $actor->role instanceof \BackedEnum ? $actor->role->value : ($actor->role ? (string) $actor->role : null);
            }

            $this->eventRecorder->record(
                appointmentId: $id,
                dealershipId: (string) $current->dealership_id,
                type: $eventType,
                version: $newVersion,
                occurredAt: $now,
                fromStatus: $current->status,
                toStatus: $to,
                actorUserId: $actor?->id,
                actorRole: $actorRole,
                data: $changes,
            );

            return $this->appointmentRepository->find($id) ?? throw new AppointmentNotFound($id);
        });
    }
}
