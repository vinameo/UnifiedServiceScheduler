<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\AddVehicleRequest;
use App\Http\Resources\MeResource;
use App\Http\Resources\VehicleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly CustomerServiceInterface $customerService,
    ) {}

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['dealership', 'customer']);

        return (new MeResource($user))->response()->setStatusCode(200);
    }

    public function vehicles(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vehicles = $this->customerService->myVehicles($user);

        return response()->json([
            'data' => VehicleResource::collection($vehicles),
        ]);
    }

    public function addVehicle(AddVehicleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vehicle = $this->customerService->addVehicle($user, $request->toPayload());

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(201);
    }
}
