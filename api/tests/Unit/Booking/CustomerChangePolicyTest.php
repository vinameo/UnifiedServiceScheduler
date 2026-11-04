<?php

namespace Tests\Unit\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\CustomerChangePolicy;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CustomerChangePolicyTest extends TestCase
{
    public function test_confirmed_more_than_two_hours_is_within_window(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00Z');
        $start = CarbonImmutable::parse('2026-11-02 10:30:00Z');

        $this->assertTrue(CustomerChangePolicy::canChange(AppointmentStatus::CONFIRMED, $start, $now));
    }

    public function test_confirmed_exactly_two_hours_is_within_window(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00Z');
        $start = CarbonImmutable::parse('2026-11-02 10:00:00Z');

        $this->assertTrue(CustomerChangePolicy::canChange(AppointmentStatus::CONFIRMED, $start, $now));
    }

    public function test_confirmed_less_than_two_hours_is_outside_window(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00Z');
        $start = CarbonImmutable::parse('2026-11-02 09:59:59Z');

        $this->assertFalse(CustomerChangePolicy::canChange(AppointmentStatus::CONFIRMED, $start, $now));
    }

    public function test_held_has_no_time_limit(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00Z');
        $start = CarbonImmutable::parse('2026-11-02 08:30:00Z');

        $this->assertTrue(CustomerChangePolicy::canChange(AppointmentStatus::HELD, $start, $now));
    }

    public function test_non_changeable_statuses_return_false(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00Z');
        $start = CarbonImmutable::parse('2026-11-02 12:00:00Z');

        $statuses = [
            AppointmentStatus::CHECKED_IN,
            AppointmentStatus::IN_PROGRESS,
            AppointmentStatus::COMPLETED,
            AppointmentStatus::CANCELLED,
            AppointmentStatus::NO_SHOW,
            AppointmentStatus::EXPIRED,
        ];

        foreach ($statuses as $status) {
            $this->assertFalse(
                CustomerChangePolicy::canChange($status, $start, $now),
                "Status {$status->value} should not be within customer change window"
            );
        }
    }

    public function test_is_within_customer_change_window_with_model(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00Z');

        $appointment = new Appointment;
        $appointment->status = AppointmentStatus::CONFIRMED;
        $appointment->start_at = CarbonImmutable::parse('2026-11-02 11:00:00Z');

        $this->assertTrue(CustomerChangePolicy::isWithinCustomerChangeWindow($appointment, $now));

        $appointment->start_at = CarbonImmutable::parse('2026-11-02 09:00:00Z');
        $this->assertFalse(CustomerChangePolicy::isWithinCustomerChangeWindow($appointment, $now));
    }
}
