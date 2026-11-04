<?php

namespace App\Domain\Booking;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class CustomerChangePolicy
{
    public const int CHANGE_WINDOW_HOURS = 2;

    /**
     * Determines whether a customer is allowed to cancel or reschedule an appointment at $now.
     *
     * Allowed when:
     * - Status is HELD (holds have no 2-hour limit, D-07.3); OR
     * - Status is CONFIRMED and start_at - now >= 2 hours (INV-07.3, BR-07.2).
     *
     * Returns false for all other statuses or when less than 2 hours remain.
     */
    public static function isWithinCustomerChangeWindow(Appointment $appointment, CarbonInterface $now): bool
    {
        return self::canChange($appointment->status, $appointment->start_at, $now);
    }

    /**
     * Pure rule check on status, start time and current time.
     */
    public static function canChange(
        AppointmentStatus $status,
        CarbonInterface $startAt,
        CarbonInterface $now,
    ): bool {
        if ($status === AppointmentStatus::HELD) {
            return true;
        }

        if ($status !== AppointmentStatus::CONFIRMED) {
            return false;
        }

        $start = CarbonImmutable::instance($startAt)->setTimezone('UTC');
        $nowImmutable = CarbonImmutable::instance($now)->setTimezone('UTC');

        // start - now >= 2 hours <=> now + 2 hours <= start
        return $nowImmutable->addHours(self::CHANGE_WINDOW_HOURS)->lessThanOrEqualTo($start);
    }
}
