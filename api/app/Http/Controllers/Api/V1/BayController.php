<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\MasterData\Services\MasterDataServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\BayResource;
use App\Models\Dealership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BayController extends Controller
{
    public function __construct(
        private readonly MasterDataServiceInterface $masterDataService,
    ) {}

    public function index(Request $request, Dealership $dealership): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bays = $this->masterDataService->bays($user, $dealership);

        return response()->json([
            'data' => BayResource::collection($bays),
        ]);
    }
}
