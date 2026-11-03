<?php

namespace App\Http\Resources;

use App\Domain\Booking\DayAvailability;
use App\Domain\Booking\SlotState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DayAvailability $resource
 */
class AvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DayAvailability $availability */
        $availability = $this->resource;

        return [
            'date' => $availability->date,
            'timezone' => $availability->timezone,
            'closed' => $availability->closed,
            'opensAt' => $availability->opensAt,
            'closesAt' => $availability->closesAt,
            'stepMinutes' => $availability->stepMinutes,
            'durationMinutes' => $availability->durationMinutes,
            'freeCount' => $availability->freeCount,
            'slots' => array_map(fn (SlotState $slot): array => [
                'start' => $slot->start->setTimezone($availability->timezone)->toIso8601String(),
                'end' => $slot->end->setTimezone($availability->timezone)->toIso8601String(),
                'available' => $slot->available,
                'reason' => $slot->reason,
            ], $availability->slots),
        ];
    }
}
