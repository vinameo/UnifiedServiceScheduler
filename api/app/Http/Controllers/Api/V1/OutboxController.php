<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\Services\OutboxServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListOutboxMessagesRequest;
use App\Http\Resources\OutboxMessageResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboxController extends Controller
{
    public function __construct(
        private readonly OutboxServiceInterface $outboxService,
    ) {}

    public function index(ListOutboxMessagesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = $this->outboxService->list(
            admin: $user,
            status: $request->status(),
            channel: $request->channel(),
            eventType: $request->eventType(),
            appointmentId: $request->appointmentId(),
            cursor: $request->cursor(),
            limit: $request->limit(),
        );

        return response()->json([
            'data' => OutboxMessageResource::collection($page->items),
            'meta' => [
                'nextCursor' => $page->nextCursor,
                'limit' => $page->limit,
                'total' => $page->total,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $summary = $this->outboxService->summary(admin: $user);

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function retry(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $messageId = (string) ($request->route('id') ?? $request->route('message') ?? $id);
        $now = CarbonImmutable::now();

        $message = $this->outboxService->retry(
            dealershipId: (string) $user->dealership_id,
            messageId: $messageId,
            now: $now,
        );

        return response()->json([
            'data' => new OutboxMessageResource($message),
        ]);
    }
}
