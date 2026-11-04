<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Mail\AppointmentNotification;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
{
    public function test_subject_lines_for_different_event_types(): void
    {
        $recipient = new Recipient(
            phone: '+84901234567',
            email: 'user@example.com',
            name: 'Hùng',
        );

        $events = [
            OutboxEventType::AppointmentConfirmed->value => 'Appointment Confirmed: APT-100',
            OutboxEventType::AppointmentCancelled->value => 'Appointment Cancelled: APT-100',
            OutboxEventType::AppointmentRescheduled->value => 'Appointment Rescheduled: APT-100',
            OutboxEventType::AppointmentReminder->value => 'Appointment Reminder: APT-100',
            OutboxEventType::AppointmentReassigned->value => 'Appointment Update: APT-100',
        ];

        foreach ($events as $typeValue => $expectedSubject) {
            $msg = new OutboxMessage([
                'id' => 'm1',
                'event_id' => 'e1',
                'dealership_id' => 'd1',
                'event_type' => OutboxEventType::from($typeValue),
                'channel' => OutboxChannel::EMAIL,
                'payload' => ['reference' => 'APT-100'],
            ]);

            $notification = new AppointmentNotification($msg, $recipient);
            $this->assertSame($expectedSubject, $notification->envelope()->subject);
        }
    }

    public function test_body_contains_greeting_reference_and_times(): void
    {
        $recipient = new Recipient(
            phone: '+84901234567',
            email: 'user@example.com',
            name: 'Minh',
        );

        $msg = new OutboxMessage([
            'id' => 'm2',
            'event_id' => 'e2',
            'dealership_id' => 'd1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => [
                'reference' => 'APT-20261104-9999',
                'start' => '2026-11-05T09:00:00+07:00',
                'end' => '2026-11-05T10:30:00+07:00',
            ],
        ]);

        $notification = new AppointmentNotification($msg, $recipient);
        $this->assertStringContainsString('Hello Minh,', $notification->bodyText);
        $this->assertStringContainsString('APT-20261104-9999', $notification->bodyText);
        $this->assertStringContainsString('2026-11-05T09:00:00+07:00', $notification->bodyText);
        $this->assertStringContainsString('2026-11-05T10:30:00+07:00', $notification->bodyText);
    }
}
