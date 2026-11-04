<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Monitoring\Services\MonitoringServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\MonitoringErrorsRequest;
use App\Http\Requests\MonitoringSummaryRequest;
use App\Http\Resources\ApiEventResource;
use App\Http\Resources\MonitoringSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringServiceInterface $monitoringService,
    ) {}

    public function summary(MonitoringSummaryRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $summary = $this->monitoringService->getSummary(
            admin: $user,
            range: $request->range(),
        );

        return response()->json([
            'data' => new MonitoringSummaryResource($summary),
        ]);
    }

    public function errors(MonitoringErrorsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = $this->monitoringService->listErrors(
            admin: $user,
            correlationId: $request->correlationId(),
            cursor: $request->cursor(),
            limit: $request->limit(),
        );

        return response()->json([
            'data' => ApiEventResource::collection($page->items),
            'meta' => [
                'nextCursor' => $page->nextCursor,
                'limit' => $page->limit,
                'total' => $page->total,
            ],
        ]);
    }
}
