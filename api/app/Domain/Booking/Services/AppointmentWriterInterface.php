<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\CandidatePair;
use App\Domain\Booking\NewAppointment;
use App\Models\Appointment;
use Carbon\CarbonImmutable;

interface AppointmentWriterInterface
{
    /**
     * @param  list<CandidatePair>  $pairs
     */
    public function create(NewAppointment $data, array $pairs, CarbonImmutable $now): Appointment;
}
