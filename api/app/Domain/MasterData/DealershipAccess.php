<?php

namespace App\Domain\MasterData;

use App\Domain\Auth\Role;
use App\Domain\MasterData\Exceptions\DealershipNotFound;
use App\Models\Dealership;
use App\Models\User;
use Illuminate\Support\Str;

class DealershipAccess
{
    /**
     * Resolve a dealership for the given user, enforcing INV-01.3.
     *
     * @throws DealershipNotFound
     */
    public function resolveFor(?User $user, string $id): Dealership
    {
        if (! Str::isUuid($id)) {
            throw new DealershipNotFound;
        }

        $dealership = Dealership::find($id);
        if ($dealership === null) {
            throw new DealershipNotFound;
        }

        if ($user !== null) {
            $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);
            if ($role?->isStaff()) {
                if ($user->dealership_id !== $dealership->id) {
                    throw new DealershipNotFound;
                }
            }
        }

        return $dealership;
    }
}
