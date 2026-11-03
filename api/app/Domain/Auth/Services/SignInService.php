<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\InvalidCredentials;
use App\Domain\Auth\Exceptions\TooManyAttempts;
use App\Domain\Auth\Portal;
use App\Domain\Auth\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SignInService implements SignInServiceInterface
{
    private const DUMMY_HASH = '$2y$12$IZEIBAzXHcWMZk8oJKpqAe4z9/QU5UH0P.sNtXLKyHWG79Ov0.HIy';

    public function signIn(string $email, string $password, Portal $portal): User
    {
        $normalizedEmail = trim(strtolower($email));
        $throttleKey = 'sign_in:'.hash('sha256', $normalizedEmail);

        // Atomically increment counter first (INV-00.5)
        $attempts = RateLimiter::hit($throttleKey, 15 * 60);

        if ($attempts > 5) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Log::warning('Sign-in failed', ['outcome' => 'TOO_MANY_ATTEMPTS']);

            throw new TooManyAttempts($seconds);
        }

        $user = User::with(['dealership', 'customer'])->where('email', $normalizedEmail)->first();

        if ($user === null) {
            // Constant-time check to prevent timing attack enumeration (INV-00.4)
            Hash::check($password, self::DUMMY_HASH);
            Log::warning('Sign-in failed', ['outcome' => 'INVALID_CREDENTIALS']);

            throw new InvalidCredentials;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);
        $isStaff = $role?->isStaff() ?? false;

        if ($portal === Portal::STAFF && ! $isStaff) {
            Hash::check($password, $user->password);
            Log::warning('Sign-in failed', ['outcome' => 'INVALID_CREDENTIALS']);

            throw new InvalidCredentials;
        }

        if ($portal === Portal::CUSTOMER && $isStaff) {
            Hash::check($password, $user->password);
            Log::warning('Sign-in failed', ['outcome' => 'INVALID_CREDENTIALS']);

            throw new InvalidCredentials;
        }

        if (! Hash::check($password, $user->password)) {
            Log::warning('Sign-in failed', ['outcome' => 'INVALID_CREDENTIALS']);

            throw new InvalidCredentials;
        }

        // Successful authentication clears the rate limiter counter
        RateLimiter::clear($throttleKey);

        Auth::guard('web')->login($user);
        session()->regenerate();

        return $user;
    }
}
