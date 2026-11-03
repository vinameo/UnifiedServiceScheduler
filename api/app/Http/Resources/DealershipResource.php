<?php

namespace App\Http\Resources;

use App\Models\Dealership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dealership
 */
class DealershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'branchName' => $this->branch_name,
            'timezone' => $this->timezone,
            'phone' => $this->phone,
            'slotStepMinutes' => 15,
            'openingHours' => DealershipOpeningHourResource::collection(
                $this->relationLoaded('openingHours') ? $this->openingHours : []
            ),
        ];
    }
}
