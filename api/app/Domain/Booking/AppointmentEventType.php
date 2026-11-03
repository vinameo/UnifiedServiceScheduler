<?php

namespace App\Domain\Booking;

enum AppointmentEventType: string
{
    case CREATED = 'CREATED';
    case HELD = 'HELD';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case RESCHEDULED = 'RESCHEDULED';
    case EXPIRED = 'EXPIRED';
    case CHECKED_IN = 'CHECKED_IN';
    case STARTED = 'STARTED';
    case COMPLETED = 'COMPLETED';
    case NO_SHOW = 'NO_SHOW';
    case EXTENDED = 'EXTENDED';
    case REASSIGNED = 'REASSIGNED';
    case CONTACT_REQUIRED = 'CONTACT_REQUIRED';
}
