<?php

namespace App\Http;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;

class AppointmentResponder
{
    public static function created(Appointment $appointment): JsonResponse
    {
        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201)
            ->header('Location', "/api/v1/appointments/{$appointment->id}")
            ->header('ETag', "\"{$appointment->version}\"");
    }

    public static function ok(Appointment $appointment): JsonResponse
    {
        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(200)
            ->header('ETag', "\"{$appointment->version}\"");
    }
}
