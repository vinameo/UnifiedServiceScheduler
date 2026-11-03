<?php

namespace App\Domain\Booking\Exceptions;

use App\Domain\Booking\AppointmentStatus;
use App\Support\Problem\ProblemException;

class InvalidStatusTransition extends ProblemException
{
    public function __construct(AppointmentStatus $currentStatus, ?AppointmentStatus $targetStatus = null)
    {
        $detail = $targetStatus !== null
            ? "Cannot transition appointment from {$currentStatus->value} to {$targetStatus->value}."
            : "Cannot transition appointment from {$currentStatus->value}.";

        parent::__construct(
            status: 422,
            problemCode: 'INVALID_STATUS_TRANSITION',
            title: 'Invalid appointment status transition.',
            detail: $detail,
            extra: [
                'currentStatus' => $currentStatus->value,
            ],
        );
    }
}
