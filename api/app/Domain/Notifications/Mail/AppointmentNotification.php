<?php

namespace App\Domain\Notifications\Mail;

use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    public string $bodyText;

    public function __construct(
        public readonly OutboxMessage $outboxMessage,
        public readonly Recipient $recipient,
    ) {
        $this->subjectLine = $this->resolveSubject();
        $this->bodyText = $this->resolveBody();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->bodyText)),
        );
    }

    private function resolveSubject(): string
    {
        $payload = $this->outboxMessage->payload;
        $appointmentRef = $this->outboxMessage->relationLoaded('appointment')
            ? $this->outboxMessage->appointment?->reference
            : null;
        $reference = (isset($payload['reference']) && is_string($payload['reference']))
            ? $payload['reference']
            : ($appointmentRef ?? 'Service Appointment');

        return match ($this->outboxMessage->event_type) {
            OutboxEventType::AppointmentConfirmed => "Appointment Confirmed: {$reference}",
            OutboxEventType::AppointmentCancelled => "Appointment Cancelled: {$reference}",
            OutboxEventType::AppointmentRescheduled => "Appointment Rescheduled: {$reference}",
            OutboxEventType::AppointmentReminder => "Appointment Reminder: {$reference}",
            OutboxEventType::AppointmentReassigned => "Appointment Update: {$reference}",
        };
    }

    private function resolveBody(): string
    {
        $payload = $this->outboxMessage->payload;
        $appointment = $this->outboxMessage->relationLoaded('appointment')
            ? $this->outboxMessage->appointment
            : null;
        $reference = (isset($payload['reference']) && is_string($payload['reference']))
            ? $payload['reference']
            : ($appointment !== null ? $appointment->reference : 'Service Appointment');
        $start = (isset($payload['start']) && is_string($payload['start']))
            ? $payload['start']
            : ($appointment !== null ? $appointment->start_at->toIso8601String() : 'N/A');
        $end = (isset($payload['end']) && is_string($payload['end']))
            ? $payload['end']
            : ($appointment !== null ? $appointment->end_at->toIso8601String() : 'N/A');
        $eventType = $this->outboxMessage->event_type->value;

        $greeting = $this->recipient->name ? "Hello {$this->recipient->name}," : 'Hello,';

        $lines = [
            $greeting,
            '',
            "Event: {$eventType}",
            "Appointment Reference: {$reference}",
        ];

        if ($start !== 'N/A') {
            $lines[] = "Scheduled Start: {$start}";
        }
        if ($end !== 'N/A') {
            $lines[] = "Scheduled End: {$end}";
        }

        $lines[] = '';
        $lines[] = 'Thank you for choosing our service.';

        return implode("\n", $lines);
    }
}
