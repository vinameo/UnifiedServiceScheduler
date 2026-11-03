<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\AppointmentQueryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\DealershipAppointmentsRequest;
use App\Http\Resources\AppointmentListItemResource;
use App\Models\Dealership;
use Illuminate\Http\JsonResponse;

class DealershipAppointmentController extends Controller
{
    public function __construct(
        private AppointmentQueryServiceInterface $appointmentQueryService,
    ) {}

    public function index(Dealership $dealership, DealershipAppointmentsRequest $request): JsonResponse
    {
        $page = $this->appointmentQueryService->listForDealershipDay(
            dealership: $dealership,
            date: $request->dateString(),
            technicianId: $request->technicianId(),
            bayId: $request->bayId(),
            statuses: $request->statuses(),
            limit: $request->limit(),
            cursor: $request->cursor(),
        );

        return response()->json([
            'data' => AppointmentListItemResource::collection($page->items),
            'meta' => [
                'nextCursor' => $page->nextCursor,
                'limit' => $page->limit,
                'total' => $page->total,
            ],
        ]);
    }
}
