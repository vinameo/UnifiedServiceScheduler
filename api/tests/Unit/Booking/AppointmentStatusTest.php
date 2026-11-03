<?php

namespace Tests\Unit\Booking;

use App\Domain\Booking\AppointmentStatus;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    public function test_AC_04_15_transition_table(): void
    {
        $allowedTransitions = [
            AppointmentStatus::HELD->value => [
                AppointmentStatus::CONFIRMED->value,
                AppointmentStatus::CANCELLED->value,
                AppointmentStatus::EXPIRED->value,
            ],
            AppointmentStatus::CONFIRMED->value => [
                AppointmentStatus::CHECKED_IN->value,
                AppointmentStatus::CANCELLED->value,
                AppointmentStatus::NO_SHOW->value,
            ],
            AppointmentStatus::CHECKED_IN->value => [
                AppointmentStatus::IN_PROGRESS->value,
            ],
            AppointmentStatus::IN_PROGRESS->value => [
                AppointmentStatus::COMPLETED->value,
            ],
            AppointmentStatus::COMPLETED->value => [],
            AppointmentStatus::CANCELLED->value => [],
            AppointmentStatus::NO_SHOW->value => [],
            AppointmentStatus::EXPIRED->value => [],
        ];

        $allStatuses = AppointmentStatus::cases();
        $this->assertCount(8, $allStatuses);

        foreach ($allStatuses as $from) {
            foreach ($allStatuses as $to) {
                $expected = in_array($to->value, $allowedTransitions[$from->value], true);
                $this->assertSame(
                    $expected,
                    $from->canMoveTo($to),
                    "Transition from {$from->value} to {$to->value} expected ".($expected ? 'true' : 'false')
                );
            }
        }

        // holdsSlot matches CONTEXT.md
        $this->assertTrue(AppointmentStatus::HELD->holdsSlot());
        $this->assertTrue(AppointmentStatus::CONFIRMED->holdsSlot());
        $this->assertTrue(AppointmentStatus::CHECKED_IN->holdsSlot());
        $this->assertTrue(AppointmentStatus::IN_PROGRESS->holdsSlot());

        $this->assertFalse(AppointmentStatus::COMPLETED->holdsSlot());
        $this->assertFalse(AppointmentStatus::CANCELLED->holdsSlot());
        $this->assertFalse(AppointmentStatus::NO_SHOW->holdsSlot());
        $this->assertFalse(AppointmentStatus::EXPIRED->holdsSlot());

        // Labels match CONTEXT.md
        $this->assertSame('On hold', AppointmentStatus::HELD->label());
        $this->assertSame('Confirmed', AppointmentStatus::CONFIRMED->label());
        $this->assertSame('Checked in', AppointmentStatus::CHECKED_IN->label());
        $this->assertSame('In progress', AppointmentStatus::IN_PROGRESS->label());
        $this->assertSame('Completed', AppointmentStatus::COMPLETED->label());
        $this->assertSame('Cancelled', AppointmentStatus::CANCELLED->label());
        $this->assertSame('No-show', AppointmentStatus::NO_SHOW->label());
        $this->assertSame('Expired', AppointmentStatus::EXPIRED->label());
    }
}
