<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Role;
use App\Models\User;

/**
 * Data access for users. The only layer that talks to the User Eloquent model.
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function create(string $name, string $email, string $password, Role $role, ?string $dealershipId = null): User;
}
