<?php

namespace App\Domain\Auth\Services;

use App\Models\User;

interface SignUpServiceInterface
{
    public function signUp(string $email, string $password, string $fullName, string $phone): User;
}
