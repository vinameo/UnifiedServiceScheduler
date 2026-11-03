<?php

namespace App\Http\Resources;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
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
        $expiresAt = $this->expires_at !== null ? CarbonImmutable::parse($this->expires_at)->setTimezone($tz)->toIso8601String() : null;
        $createdAt = CarbonImmutable::parse($this->created_at)->setTimezone($tz)->toIso8601String();

        $cancellation = null;
        if ($this->status === AppointmentStatus::CANCELLED && $this->cancelled_at !== null) {
            $cancelledAt = CarbonImmutable::parse($this->cancelled_at)->setTimezone($tz)->toIso8601String();
            $cancelledByUser = $this->cancelledByUser;
            $cancellation = [
                'reason' => $this->cancel_reason,
                'note' => $this->cancel_note,
                'at' => $cancelledAt,
                'byRole' => $cancelledByUser?->role instanceof \BackedEnum ? $cancelledByUser->role->value : (string) $cancelledByUser?->role,
            ];
        }

        $user = $request->user();
        $isStaff = $user !== null && method_exists($user, 'isStaff') ? $user->isStaff() : false;
        $createdBy = null;
        if ($isStaff && $this->createdByUser !== null) {
            $createdByUser = $this->createdByUser;
            $createdBy = [
                'name' => $createdByUser->name,
                'role' => $createdByUser->role instanceof \BackedEnum ? $createdByUser->role->value : (string) $createdByUser->role,
            ];
        }

        $vehicleData = null;
        if ($this->vehicle !== null) {
            $vehicle = $this->vehicle;
            $vehicleData = [
                'id' => $vehicle->id,
                'licencePlate' => $vehicle->plate_display ?? $vehicle->plate_key,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'vin' => $vehicle->vin,
            ];
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'expiresAt' => $expiresAt,
            'version' => $this->version,
            'dealership' => [
                'id' => $dealership?->id,
                'name' => $dealership?->name,
                'branchName' => $dealership?->branch_name,
                'timezone' => $dealership?->timezone,
                'phone' => $dealership?->phone,
            ],
            'serviceType' => [
                'id' => $this->serviceType?->id,
                'code' => $this->serviceType?->code,
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
                'bayType' => $this->bay?->bay_type instanceof \BackedEnum ? $this->bay->bay_type->value : (string) $this->bay?->bay_type,
            ],
            'customer' => [
                'id' => $this->customer?->id,
                'fullName' => $this->customer?->full_name,
                'phone' => $this->customer?->phone,
            ],
            'vehicle' => $vehicleData,
            'reminderOptIn' => (bool) $this->reminder_opt_in,
            'source' => $this->source,
            'cancellation' => $cancellation,
            'contactRequiredReason' => $this->contact_required_reason,
            'createdAt' => $createdAt,
            'createdBy' => $createdBy,
        ];
    }
}
