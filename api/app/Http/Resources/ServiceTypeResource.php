<?php

namespace App\Http\Resources;

use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceType
 */
class ServiceTypeResource extends JsonResource
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
            'description' => $this->description,
            'durationMinutes' => $this->duration_minutes,
            'requiredLevel' => $this->required_level,
            'requiredBayType' => $this->required_bay_type->value,
            'active' => (bool) $this->active,
        ];
    }
}
