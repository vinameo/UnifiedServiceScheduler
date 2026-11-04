<?php

namespace App\Domain\Workshop\Services;

use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;

interface WorkshopServiceInterface
{
    public function checkIn(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;

    public function start(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;

    public function complete(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;

    public function markNoShow(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment;

    public function extend(User $actor, string $appointmentId, int $expectedVersion, int $minutes, CarbonImmutable $now): ExtendResult;

    public function board(Dealership $dealership, string $date, CarbonImmutable $now): WorkshopBoardData;
}
