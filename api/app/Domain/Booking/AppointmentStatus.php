<?php

namespace App\Domain\Booking;

enum AppointmentStatus: string
{
    case HELD = 'HELD';
    case CONFIRMED = 'CONFIRMED';
    case CHECKED_IN = 'CHECKED_IN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW = 'NO_SHOW';
    case EXPIRED = 'EXPIRED';

    public function canMoveTo(self $target): bool
    {
        return match ($this) {
            self::HELD => in_array($target, [self::CONFIRMED, self::CANCELLED, self::EXPIRED], true),
            self::CONFIRMED => in_array($target, [self::CHECKED_IN, self::CANCELLED, self::NO_SHOW], true),
            self::CHECKED_IN => $target === self::IN_PROGRESS,
            self::IN_PROGRESS => $target === self::COMPLETED,
            self::COMPLETED, self::CANCELLED, self::NO_SHOW, self::EXPIRED => false,
        };
    }

    public function holdsSlot(): bool
    {
        return match ($this) {
            self::HELD, self::CONFIRMED, self::CHECKED_IN, self::IN_PROGRESS => true,
            self::COMPLETED, self::CANCELLED, self::NO_SHOW, self::EXPIRED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::HELD => 'On hold',
            self::CONFIRMED => 'Confirmed',
            self::CHECKED_IN => 'Checked in',
            self::IN_PROGRESS => 'In progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No-show',
            self::EXPIRED => 'Expired',
        };
    }
}
