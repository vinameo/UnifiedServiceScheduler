<?php

namespace App\Http\Resources;

use App\Domain\Workshop\Services\WorkshopBoardData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property WorkshopBoardData $resource
 */
class WorkshopBoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
