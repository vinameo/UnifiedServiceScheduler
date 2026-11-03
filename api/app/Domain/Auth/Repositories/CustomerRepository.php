<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\CustomerSearchResult;
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

    public function find(string $id): ?Customer
    {
        return Customer::query()->where('id', $id)->first();
    }

    public function findByUserId(int $userId): ?Customer
    {
        return Customer::query()->where('user_id', $userId)->first();
    }

    public function search(string $query, int $limit = 20): CustomerSearchResult
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $namePattern = '%'.mb_strtolower($escaped, 'UTF-8').'%';
        $prefixPattern = $escaped.'%';

        $builder = Customer::query()
            ->join('users', 'users.id', '=', 'customers.user_id')
            ->where(function ($q) use ($namePattern, $prefixPattern) {
                $q->whereRaw('lower(customers.full_name) LIKE ?', [$namePattern])
                    ->orWhereRaw('customers.phone LIKE ?', [$prefixPattern])
                    ->orWhere(function ($q2) use ($prefixPattern) {
                        $q2->where('users.role', 'CUSTOMER')
                            ->whereRaw('users.email LIKE ?', [$prefixPattern]);
                    });
            });

        $total = (clone $builder)->count();

        /** @var list<Customer> $customers */
        $customers = $builder->select('customers.*')
            ->with('user')
            ->withCount('vehicles')
            ->orderBy('customers.full_name')
            ->limit($limit)
            ->get()
            ->all();

        return new CustomerSearchResult(
            customers: $customers,
            total: $total,
        );
    }
}
