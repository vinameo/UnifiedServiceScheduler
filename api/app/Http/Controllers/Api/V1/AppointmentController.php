<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\CancelReason;
use App\Domain\Booking\Services\AppointmentQueryServiceInterface;
use App\Domain\Booking\Services\BookingServiceInterface;
use App\Http\AppointmentResponder;
use App\Http\Controllers\Controller;
use App\Http\ExpectedVersion;
use App\Http\Requests\Booking\CreateAppointmentRequest;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\ConfirmHoldRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Resources\AppointmentEventResource;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $reason = $request->validated('reason') !== null
            ? CancelReason::from($request->validated('reason'))
            : null;

        $note = $request->validated('note');

        $cancelled = $this->bookingService->cancel(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            reason: $reason,
            note: $note,
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($cancelled);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $rescheduled = $this->bookingService->reschedule(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            newStart: $request->newStart(),
            keepTechnician: $request->keepTechnician(),
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($rescheduled);
    }

    public function confirm(ConfirmHoldRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $vehicleId = (string) $request->validated('vehicleId');
        $reminderOptIn = $request->has('reminderOptIn') ? (bool) $request->validated('reminderOptIn') : true;

        $confirmed = $this->bookingService->confirmHold(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            vehicleId: $vehicleId,
            reminderOptIn: $reminderOptIn,
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($confirmed);
    }
}
