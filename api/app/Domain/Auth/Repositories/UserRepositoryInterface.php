<?php

namespace App\Domain\Auth\Repositories;

use App\Models\User;

/**
 * Data access for users. The only layer that talks to the User Eloquent model.
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;
}
