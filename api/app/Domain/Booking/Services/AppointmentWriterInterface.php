<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\CandidatePair;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\NewAppointment;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use Carbon\CarbonImmutable;

interface AppointmentWriterInterface
{
    /**
     * @param  list<CandidatePair>  $pairs
     */
    public function create(NewAppointment $data, array $pairs, CarbonImmutable $now): Appointment;

    /**
     * INV-04.7: F09 reschedule, F12 extend/reassign.
     *
     * @param  list<CandidatePair>  $pairs
     */
    public function moveTo(
        string $appointmentId,
        int $expectedVersion,
        TimeRange $newRange,
        array $pairs,
        AppointmentEventType $event,
        ?OutboxEventType $outbox,
        CarbonImmutable $now,
    ): Appointment;
}
