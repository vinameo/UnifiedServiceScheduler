<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\EmailAlreadyUsed;
use App\Domain\Auth\Exceptions\WeakPassword;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SignUpService implements SignUpServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
    ) {}

    public function signUp(string $email, string $password, string $fullName, string $phone): User
    {
        $normalizedEmail = strtolower(trim($email));

        if (mb_strlen($password) < 8 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            throw new WeakPassword;
        }

        if ($this->userRepository->findByEmail($normalizedEmail) !== null) {
            throw new EmailAlreadyUsed;
        }

        $hasLeadingPlus = str_starts_with(trim($phone), '+');
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $normalizedPhone = ($hasLeadingPlus ? '+' : '').$digits;

        try {
            /** @var User $user */
            $user = DB::transaction(function () use ($normalizedEmail, $password, $fullName, $normalizedPhone) {
                $user = $this->userRepository->create(
                    name: $fullName,
                    email: $normalizedEmail,
                    password: $password,
                    role: Role::CUSTOMER,
                    dealershipId: null,
                );

                $this->customerRepository->create(
                    userId: $user->id,
                    fullName: $fullName,
                    phone: $normalizedPhone,
                );

                return $user;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new EmailAlreadyUsed;
            }
            throw $e;
        }

        Auth::guard('web')->login($user);
        session()->regenerate();

        return $user->load('customer');
    }
}
