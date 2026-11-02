<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Role;
use App\Models\User;

final class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function create(string $name, string $email, string $password, Role $role, ?string $dealershipId = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'dealership_id' => $dealershipId,
        ]);
    }
}
