<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\BookingServiceInterface;
use App\Http\AppointmentResponder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CreateAppointmentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function __construct(
        private BookingServiceInterface $bookingService,
    ) {}

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

