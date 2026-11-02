<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Models\User;

final class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->users->findByEmail($email);
    }
}
