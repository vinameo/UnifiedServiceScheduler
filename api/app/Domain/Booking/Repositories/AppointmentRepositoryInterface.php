<?php

namespace App\Domain\Booking\Repositories;

use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

interface AppointmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insert(array $attributes): Appointment;

    public function find(string $id): ?Appointment;

    public function findVisibleTo(User $user, string $id): Appointment;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateVersioned(string $id, int $expectedVersion, array $changes, CarbonImmutable $now): int;
}
