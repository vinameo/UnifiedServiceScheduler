<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\AppointmentQueryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyAppointmentsRequest;
use App\Http\Resources\AppointmentListItemResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MyAppointmentController extends Controller
{
    public function __construct(
        private AppointmentQueryServiceInterface $appointmentQueryService,
    ) {}

    public function index(MyAppointmentsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = $this->appointmentQueryService->listForCustomer(
            user: $user,
            scope: $request->scope(),
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
