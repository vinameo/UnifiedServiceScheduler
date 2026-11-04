<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workshop\Services\WorkshopServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkshopBoardRequest;
use App\Http\Resources\WorkshopBoardResource;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class WorkshopBoardController extends Controller
{
    public function __construct(
        private WorkshopServiceInterface $workshopService,
    ) {}

    public function show(Dealership $dealership, WorkshopBoardRequest $request): JsonResponse
    {
        $now = CarbonImmutable::now();
        $date = $request->targetDate($dealership, $now);

        $board = $this->workshopService->board($dealership, $date, $now);

        return response()->json([
            'data' => (new WorkshopBoardResource($board))->resolve($request),
        ]);
    }
}
