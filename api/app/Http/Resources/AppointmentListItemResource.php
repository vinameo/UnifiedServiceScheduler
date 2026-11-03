<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dealership = $this->dealership;
        $tzName = $dealership->timezone ?? 'UTC';
        $tz = new DateTimeZone($tzName);

        $start = CarbonImmutable::parse($this->start_at)->setTimezone($tz);
        $end = CarbonImmutable::parse($this->end_at)->setTimezone($tz);

        $vehicleData = null;
        if ($this->vehicle !== null) {
            $vehicle = $this->vehicle;
            $vehicleData = [
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'licencePlate' => $vehicle->plate_display ?? $vehicle->plate_key,
            ];
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'customer' => [
                'id' => $this->customer?->id,
                'fullName' => $this->customer?->full_name,
            ],
            'vehicle' => $vehicleData,
            'serviceType' => [
                'id' => $this->serviceType?->id,
                'name' => $this->serviceType?->name,
                'durationMinutes' => $this->serviceType?->duration_minutes,
            ],
            'technician' => [
                'id' => $this->technician?->id,
                'name' => $this->technician?->name,
            ],
            'bay' => [
                'id' => $this->bay?->id,
                'code' => $this->bay?->code,
                'name' => $this->bay?->name,
            ],
            'dealership' => [
                'id' => $dealership?->id,
                'name' => $dealership?->name,
                'branchName' => $dealership?->branch_name,
                'timezone' => $dealership?->timezone,
            ],
            'contactRequiredReason' => $this->contact_required_reason,
        ];
    }
}
