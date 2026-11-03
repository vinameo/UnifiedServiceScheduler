<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\MasterData\Services\MasterDataServiceInterface;
use App\Domain\MasterData\Services\TechnicianServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTechnicianStatusRequest;
use App\Http\Resources\TechnicianResource;
use App\Models\Dealership;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    public function __construct(
        private readonly MasterDataServiceInterface $masterDataService,
        private readonly TechnicianServiceInterface $technicianService,
    ) {}

    public function index(Request $request, Dealership $dealership): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $technicians = $this->masterDataService->technicians($user, $dealership);

        return response()->json([
            'data' => TechnicianResource::collection($technicians),
        ]);
    }

    public function updateStatus(
        UpdateTechnicianStatusRequest $request,
        Dealership $dealership,
        Technician $technician,
    ): JsonResponse {
        $validated = $request->validated();
        $updated = $this->technicianService->setActive(
            $dealership,
            $technician,
            (bool) $validated['active'],
        );

        return response()->json([
            'data' => new TechnicianResource($updated),
        ]);
    }
}
