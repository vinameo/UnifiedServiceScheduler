<?php

namespace App\Http\Resources;

use App\Models\Bay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bay
 */
class BayResource extends JsonResource
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
            'bayType' => $this->bay_type->value,
            'active' => (bool) $this->active,
            'supportedServiceTypeIds' => $this->supportedServiceTypeIds ?? [],
        ];
    }
}
