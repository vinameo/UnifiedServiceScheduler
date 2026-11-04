<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workshop\Services\WorkshopServiceInterface;
use App\Http\AppointmentResponder;
use App\Http\Controllers\Controller;
use App\Http\ExpectedVersion;
use App\Http\Requests\ExtendAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopActionController extends Controller
{
    public function __construct(
        private WorkshopServiceInterface $workshopService,
    ) {}

    public function checkIn(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $result = $this->workshopService->checkIn(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($result);
    }

    public function start(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $result = $this->workshopService->start(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($result);
    }

    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $result = $this->workshopService->complete(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($result);
    }

    public function noShow(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $result = $this->workshopService->markNoShow(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            now: CarbonImmutable::now(),
        );

        return AppointmentResponder::ok($result);
    }

    public function extend(ExtendAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = ExpectedVersion::from($request);

        $result = $this->workshopService->extend(
            actor: $user,
            appointmentId: $appointment->id,
            expectedVersion: $expectedVersion,
            minutes: $request->minutes(),
            now: CarbonImmutable::now(),
        );

        return response()->json([
            'data' => [
                'appointment' => new AppointmentResource($result->appointment),
                'affected' => $result->affected,
            ],
        ], 200)->header('ETag', sprintf('"%d"', $result->appointment->version));
    }
}
