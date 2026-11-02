<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\InvalidCredentials;
use App\Domain\Auth\Portal;
use App\Domain\Auth\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SignInService implements SignInServiceInterface
{
    private const DUMMY_HASH = '$2y$10$dummyhashplaceholderforconstanttimetimingattackprevention..';

    public function signIn(string $email, string $password, Portal $portal): User
    {
        $normalizedEmail = trim(strtolower($email));
        $user = User::with(['dealership', 'customer'])->where('email', $normalizedEmail)->first();

        if ($user === null) {
            // Constant-time check to prevent timing attack enumeration (INV-00.4)
            Hash::check($password, self::DUMMY_HASH);

            throw new InvalidCredentials;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);
        $isStaff = $role?->isStaff() ?? false;

        if ($portal === Portal::STAFF && ! $isStaff) {
            Hash::check($password, $user->password);

            throw new InvalidCredentials;
        }

        if ($portal === Portal::CUSTOMER && $isStaff) {
            Hash::check($password, $user->password);

            throw new InvalidCredentials;
        }

        if (! Hash::check($password, $user->password)) {
            throw new InvalidCredentials;
        }

        Auth::guard('web')->login($user);
        session()->regenerate();

        return $user;
    }
}
