<?php

namespace App\Domain\Notifications;

enum OutboxEventType: string
{
    case AppointmentConfirmed = 'AppointmentConfirmed';
    case AppointmentCancelled = 'AppointmentCancelled';
    case AppointmentRescheduled = 'AppointmentRescheduled';
    case AppointmentReminder = 'AppointmentReminder';
    case AppointmentReassigned = 'AppointmentReassigned';
}
