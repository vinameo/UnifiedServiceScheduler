<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Auth\Role;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function findVisibleTo(User $user, string $id): Appointment
    {
        if (! Str::isUuid($id)) {
            throw new AppointmentNotFound;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

        $query = Appointment::with([
            'dealership',
            'customer',
            'vehicle',
            'serviceType',
            'technician',
            'bay',
            'createdByUser',
            'cancelledByUser',
        ])->where('id', $id);

        if ($role === Role::CUSTOMER) {
            $customerId = $user->customer->id ?? $user->customer()->value('id');
            if ($customerId === null) {
                throw new AppointmentNotFound;
            }
            $query->where('customer_id', $customerId);
        } elseif ($role?->isStaff()) {
            if ($user->dealership_id === null) {
                throw new AppointmentNotFound;
            }
            $query->where('dealership_id', $user->dealership_id);
        } else {
            throw new AppointmentNotFound;
        }

        $appointment = $query->first();

        if ($appointment === null) {
            throw new AppointmentNotFound;
        }

        return $appointment;
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
