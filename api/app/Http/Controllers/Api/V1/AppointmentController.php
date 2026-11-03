<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\AppointmentQueryServiceInterface;
use App\Domain\Booking\Services\BookingServiceInterface;
use App\Http\AppointmentResponder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CreateAppointmentRequest;
use App\Http\Resources\AppointmentEventResource;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(
        private BookingServiceInterface $bookingService,
        private AppointmentQueryServiceInterface $queryService,
    ) {}

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment = $this->queryService->show($appointment);

        return AppointmentResponder::ok($appointment);
    }

    public function history(Appointment $appointment): AnonymousResourceCollection
    {
        $events = $this->queryService->history($appointment);

        return AppointmentEventResource::collection($events);
    }

    public function store(CreateAppointmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $appointment = $this->bookingService->create(
            $request->toInput(),
            $user,
        );

        return AppointmentResponder::created($appointment);
    }
}
