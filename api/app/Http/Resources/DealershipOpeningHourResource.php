<?php

namespace App\Http\Resources;

use App\Models\DealershipOpeningHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DealershipOpeningHour
 */
class DealershipOpeningHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'weekday' => (int) $this->weekday,
            'opensAt' => substr((string) $this->opens_at, 0, 5),
            'closesAt' => substr((string) $this->closes_at, 0, 5),
        ];
    }
}
