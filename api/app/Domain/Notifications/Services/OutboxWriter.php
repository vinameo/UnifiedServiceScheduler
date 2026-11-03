<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class OutboxWriter implements OutboxWriterInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(
        string $dealershipId,
        ?string $appointmentId,
        OutboxEventType $eventType,
        array $payload,
        CarbonImmutable $now,
    ): string {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('OutboxWriter::append must be called inside an active database transaction.');
        }

        $eventId = (string) Str::uuid();
        $channels = self::channelsFor($eventType);

        foreach ($channels as $channel) {
            DB::table('outbox_messages')->insert([
                'id' => (string) Str::uuid(),
                'event_id' => $eventId,
                'dealership_id' => $dealershipId,
                'appointment_id' => $appointmentId,
                'event_type' => $eventType->value,
                'channel' => $channel->value,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $eventId;
    }

    /**
     * @return list<OutboxChannel>
     */
    public static function channelsFor(OutboxEventType $type): array
    {
        return match ($type) {
            OutboxEventType::AppointmentConfirmed,
            OutboxEventType::AppointmentCancelled,
            OutboxEventType::AppointmentRescheduled => [
                OutboxChannel::SMS,
                OutboxChannel::EMAIL,
                OutboxChannel::DMS,
            ],
            OutboxEventType::AppointmentReassigned => [
                OutboxChannel::SMS,
                OutboxChannel::DMS,
            ],
            OutboxEventType::AppointmentReminder => [
                OutboxChannel::SMS,
            ],
        };
    }
}
