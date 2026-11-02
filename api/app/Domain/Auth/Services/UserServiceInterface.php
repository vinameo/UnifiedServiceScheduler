<?php

namespace App\Domain\Auth\Services;

use App\Models\User;

/**
 * Business rules for users (F00). Controllers depend on this interface, never on a repository.
 */
interface UserServiceInterface
{
    public function findByEmail(string $email): ?User;
}
