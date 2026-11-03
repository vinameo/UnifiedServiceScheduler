<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\AvailabilityServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\NextAvailableRequest;
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

    public function next(Dealership $dealership, NextAvailableRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $nextAvailable = $this->availabilityService->next(
            dealershipId: $dealership->id,
            type: $request->serviceTypeId(),
            after: $request->afterDateTime(),
            now: CarbonImmutable::now(),
            actor: $user,
        );

        return response()->json([
            'data' => [
                'nextAvailable' => $nextAvailable !== null ? [
                    'start' => $nextAvailable->start->setTimezone($dealership->timezone)->toIso8601String(),
                    'end' => $nextAvailable->end->setTimezone($dealership->timezone)->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
