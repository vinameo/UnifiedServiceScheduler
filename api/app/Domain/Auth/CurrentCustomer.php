<?php

namespace App\Domain\Auth;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CurrentCustomer
{
    public function get(): Customer
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null || $user->role !== Role::CUSTOMER) {
            throw new AccessDeniedHttpException('This action is only available to customers.');
        }

        $customer = $user->customer;
        if ($customer === null) {
            throw new AccessDeniedHttpException('No customer profile associated with this account.');
        }

        return $customer;
    }
}
