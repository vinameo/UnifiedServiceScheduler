<?php

namespace App\Domain\Auth\Repositories;

use App\Models\Customer;
use Illuminate\Support\Str;

final class CustomerRepository implements CustomerRepositoryInterface
{
    public function create(int $userId, string $fullName, string $phone, ?string $id = null): Customer
    {
        return Customer::create([
            'id' => $id ?? (string) Str::uuid(),
            'user_id' => $userId,
            'full_name' => $fullName,
            'phone' => $phone,
        ]);
    }
}
