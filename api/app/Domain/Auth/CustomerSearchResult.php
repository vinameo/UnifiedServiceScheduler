<?php

namespace App\Domain\Auth;

use App\Models\Customer;

final readonly class CustomerSearchResult
{
    /**
     * @param  list<Customer>  $customers
     */
    public function __construct(
        public array $customers,
        public int $total,
    ) {}
}
