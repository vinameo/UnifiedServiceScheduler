<?php

namespace App\Policies;

use App\Domain\Auth\Role;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

        if ($role === Role::CUSTOMER) {
            $customerId = $user->customer->id ?? $user->customer()->value('id');

            return $customerId !== null && $appointment->customer_id === $customerId;
        }

        if ($role?->isStaff()) {
            return $user->dealership_id !== null && $appointment->dealership_id === $user->dealership_id;
        }

        return false;
    }
}
