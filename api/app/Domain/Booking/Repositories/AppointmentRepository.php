<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insert(array $attributes): Appointment
    {
        return Appointment::create($attributes);
    }

    public function find(string $id): ?Appointment
    {
        return Appointment::with([
            'dealership',
            'customer',
            'vehicle',
            'serviceType',
            'technician',
            'bay',
            'createdByUser',
            'cancelledByUser',
        ])->find($id);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateVersioned(string $id, int $expectedVersion, array $changes, CarbonImmutable $now): int
    {
        $affected = DB::table('appointments')
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->update(array_merge($changes, [
                'version' => DB::raw('version + 1'),
                'updated_at' => $now,
            ]));

        if ($affected === 0) {
            AppointmentModifiedDetected::dispatch($id, $expectedVersion);
            throw new AppointmentModified($id);
        }

        return $expectedVersion + 1;
    }
}
