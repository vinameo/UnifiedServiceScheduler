<?php

namespace App\Domain\Workshop\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

interface WorkshopServiceInterface
{
    public function checkIn(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;

    public function start(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;

    public function complete(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;
}
