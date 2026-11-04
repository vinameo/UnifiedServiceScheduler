<?php

namespace App\Http\Resources;

use App\Models\OutboxMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutboxMessage
 */
class OutboxMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tz = $this->dealership->timezone;

        $nextAttemptAt = in_array($this->status, ['FAILED', 'SENT', 'SKIPPED'], true)
            ? null
            : $this->next_attempt_at->setTimezone($tz)->toIso8601String();

        return [
            'id' => $this->id,
            'eventId' => $this->event_id,
            'eventType' => $this->event_type->value,
            'channel' => $this->channel->value,
            'status' => $this->status,
            'displayStatus' => $this->displayStatus(),
            'attempts' => (int) $this->attempts,
            'nextAttemptAt' => $nextAttemptAt,
            'lastError' => $this->last_error,
            'sentAt' => $this->sent_at?->setTimezone($tz)->toIso8601String(),
            'createdAt' => $this->created_at->setTimezone($tz)->toIso8601String(),
            'appointment' => $this->appointment !== null ? [
                'id' => $this->appointment->id,
                'reference' => $this->appointment->reference,
                'customerName' => $this->appointment->customer !== null ? $this->appointment->customer->full_name : '',
            ] : null,
        ];
    }
}
