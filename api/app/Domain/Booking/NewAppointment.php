<?php

namespace App\Domain\Booking;

use App\Domain\Allocation\TimeRange;
use Carbon\CarbonImmutable;

final readonly class NewAppointment
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public ?string $vehicleId,
        public string $serviceTypeId,
        public TimeRange $range,
        public AppointmentStatus $status = AppointmentStatus::CONFIRMED,
        public ?CarbonImmutable $expiresAt = null,
        public bool $reminderOptIn = true,
        public string $source = 'CUSTOMER',
        public int $createdByUserId = 1,
    ) {}
}
