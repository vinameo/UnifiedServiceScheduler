<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\CancelReason;
use App\Domain\Booking\CreateBookingInput;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

interface BookingServiceInterface
{
    public function create(CreateBookingInput $input, User $actor): Appointment;

    public function cancel(
        User $actor,
        string $appointmentId,
        int $expectedVersion,
        ?CancelReason $reason,
        ?string $note,
        CarbonImmutable $now,
    ): Appointment;
}
