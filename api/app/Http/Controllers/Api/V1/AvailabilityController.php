<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\AvailabilityServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(
        private AvailabilityServiceInterface $availabilityService,
    ) {}

    public function availability(Dealership $dealership, AvailabilityRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $availability = $this->availabilityService->forDay(
            dealershipId: $dealership->id,
            type: $request->serviceTypeId(),
            localDate: $request->dateString(),
            now: CarbonImmutable::now(),
            excludeAppointmentId: $request->excludeAppointmentId(),
            actor: $user,
        );

        return response()->json([
            'data' => new AvailabilityResource($availability),
        ]);
    }
}
