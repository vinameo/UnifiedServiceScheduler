<?php

namespace App\Domain\MasterData\Exceptions;

use App\Support\Problem\ProblemException;

class TechnicianHasBookings extends ProblemException
{
    /**
     * @param  list<array{id: string, reference: string, start: string, end: string, customerName: string}>  $appointments
     */
    public function __construct(
        int $count,
        array $appointments = [],
        string $technicianName = 'This technician',
    ) {
        parent::__construct(
            status: 409,
            problemCode: 'TECHNICIAN_HAS_BOOKINGS',
            title: 'Technician has upcoming appointments.',
            detail: "{$technicianName} still has {$count} upcoming appointments. Move or cancel them before disabling.",
            extra: [
                'count' => $count,
                'appointments' => $appointments,
            ],
        );
    }
}
