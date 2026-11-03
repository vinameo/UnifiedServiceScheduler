<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\MasterData\Services\MasterDataServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\DealershipResource;
use App\Models\Dealership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealershipController extends Controller
{
    public function __construct(
        private readonly MasterDataServiceInterface $masterDataService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $dealerships = $this->masterDataService->listDealerships($user);

        return response()->json([
            'data' => DealershipResource::collection($dealerships),
        ]);
    }

    public function show(Dealership $dealership): JsonResponse
    {
        $dealership->loadMissing(['openingHours' => fn ($query) => $query->orderBy('weekday')]);

        return response()->json([
            'data' => new DealershipResource($dealership),
        ]);
    }
}
