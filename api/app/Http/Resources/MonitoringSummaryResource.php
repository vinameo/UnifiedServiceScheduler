<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
class MonitoringSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'range' => $this->resource['range'],
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'health' => [
                'app' => [
                    'status' => $this->resource['health']['app']['status'],
                    'bookingP95Ms' => (int) $this->resource['health']['app']['bookingP95Ms'],
                ],
                'database' => [
                    'status' => $this->resource['health']['database']['status'],
                    'connectionsUsed' => (int) $this->resource['health']['database']['connectionsUsed'],
                    'connectionsMax' => (int) $this->resource['health']['database']['connectionsMax'],
                ],
                'outbox' => [
                    'pending' => (int) $this->resource['health']['outbox']['pending'],
                    'oldestPendingAgeSeconds' => (int) $this->resource['health']['outbox']['oldestPendingAgeSeconds'],
                ],
            ],
            'bookings' => [
                'total' => (int) $this->resource['bookings']['total'],
                'confirmed' => (int) $this->resource['bookings']['confirmed'],
                'noAvailability' => (int) $this->resource['bookings']['noAvailability'],
                'validationError' => (int) $this->resource['bookings']['validationError'],
                'error' => (int) $this->resource['bookings']['error'],
                'p95Ms' => (int) $this->resource['bookings']['p95Ms'],
                'allocationRetries' => (int) $this->resource['bookings']['allocationRetries'],
                'idempotentReplays' => (int) $this->resource['bookings']['idempotentReplays'],
                'appointmentModified' => (int) $this->resource['bookings']['appointmentModified'],
            ],
            'recentErrors' => ApiEventResource::collection($this->resource['recentErrors']),
        ];
    }
}
