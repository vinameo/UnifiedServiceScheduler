<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Portal;
use App\Models\User;

interface SignInServiceInterface
{
    public function signIn(string $email, string $password, Portal $portal): User;
}
