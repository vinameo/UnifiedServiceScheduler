<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\MasterData\Services\MasterDataServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceTypeResource;
use App\Models\Dealership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function __construct(
        private readonly MasterDataServiceInterface $masterDataService,
    ) {}

    public function index(Request $request, Dealership $dealership): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $includeInactive = $request->has('includeInactive')
            ? $request->boolean('includeInactive')
            : true;

        $services = $this->masterDataService->serviceTypes($user, $dealership, $includeInactive);

        return response()->json([
            'data' => ServiceTypeResource::collection($services),
        ]);
    }
}
