<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Repositories\OutboxMessageRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReminderQueue implements ReminderQueueInterface
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly OutboxMessageRepositoryInterface $outboxRepository,
    ) {}

    public function queue(CarbonImmutable $now): int
    {
        $nowUtc = $now->utc();
        $from = $nowUtc->addHours(23);
        $to = $nowUtc->addHours(25);

        return DB::transaction(function () use ($nowUtc, $from, $to) {
            $candidates = $this->appointmentRepository->findReminderCandidates($from, $to);

            $queuedCount = 0;
            foreach ($candidates as $appointment) {
                $startAt = CarbonImmutable::parse($appointment->start_at)->utc();
                $endAt = CarbonImmutable::parse($appointment->end_at)->utc();
                $startIso = $startAt->toIso8601String();
                $endIso = $endAt->toIso8601String();

                $dedupeKey = "reminder:{$appointment->id}:{$startIso}";

                $payload = [
                    'appointmentId' => (string) $appointment->id,
                    'reference' => (string) $appointment->reference,
                    'customerId' => (string) $appointment->customer_id,
                    'start' => $startIso,
                    'end' => $endIso,
                ];

                $inserted = $this->outboxRepository->insertIfAbsent([
                    'id' => (string) Str::uuid(),
                    'event_id' => (string) Str::uuid(),
                    'dealership_id' => (string) $appointment->dealership_id,
                    'appointment_id' => (string) $appointment->id,
                    'event_type' => OutboxEventType::AppointmentReminder->value,
                    'channel' => OutboxChannel::SMS->value,
                    'payload' => $payload,
                    'status' => 'PENDING',
                    'attempts' => 0,
                    'next_attempt_at' => $nowUtc->toIso8601String(),
                    'dedupe_key' => $dedupeKey,
                    'created_at' => $nowUtc->toIso8601String(),
                    'updated_at' => $nowUtc->toIso8601String(),
                ]);

                if ($inserted) {
                    $queuedCount++;
                }
            }

            return $queuedCount;
        });
    }
}
