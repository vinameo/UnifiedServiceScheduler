<?php

namespace App\Domain\Booking\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AppointmentModifiedDetected
{
    use Dispatchable;

    public function __construct(
        public readonly string $appointmentId,
        public readonly int $expectedVersion,
    ) {}
}
