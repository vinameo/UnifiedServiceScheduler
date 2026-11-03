<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentEventRecorder implements AppointmentEventRecorderInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(
        string $appointmentId,
        string $dealershipId,
        AppointmentEventType $type,
        int $version,
        CarbonImmutable $occurredAt,
        ?AppointmentStatus $fromStatus = null,
        ?AppointmentStatus $toStatus = null,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        array $data = [],
    ): string {
        $id = (string) Str::uuid();

        DB::table('appointment_events')->insert([
            'id' => $id,
            'appointment_id' => $appointmentId,
            'dealership_id' => $dealershipId,
            'type' => $type->value,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'version' => $version,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt,
        ]);

        return $id;
    }
}
