<?php

namespace App\Domain\Booking;

use Carbon\CarbonImmutable;

final readonly class CreateBookingInput
{
    public function __construct(
        public ?string $dealershipId,
        public ?string $customerId,
        public ?string $vehicleId,
        public string $serviceTypeId,
        public CarbonImmutable $desiredStart,
        public bool $reminderOptIn = true,
        public string $mode = 'CONFIRM',
    ) {}
}
