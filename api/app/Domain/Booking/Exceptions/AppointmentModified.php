<?php

namespace App\Domain\Booking\Exceptions;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Support\Problem\ProblemException;

class AppointmentModified extends ProblemException
{
    public function __construct(string $id, ?Appointment $latest = null)
    {
        $extra = [];
        $headers = [];

        if ($latest === null) {
            $latest = Appointment::with(['dealership', 'customer', 'vehicle', 'serviceType', 'technician', 'bay', 'createdByUser'])->find($id);
        }

        if ($latest !== null) {
            $extra['appointment'] = (new AppointmentResource($latest))->resolve();
            $headers['ETag'] = "\"{$latest->version}\"";
        }

        parent::__construct(
            status: 412,
            problemCode: 'APPOINTMENT_MODIFIED',
            title: 'This appointment was modified by another request.',
            extra: $extra,
            headers: $headers,
        );
    }
}
