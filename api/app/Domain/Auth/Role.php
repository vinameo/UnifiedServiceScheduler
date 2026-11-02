<?php

namespace App\Domain\Auth;

enum Role: string
{
    case CUSTOMER = 'CUSTOMER';
    case SERVICE_ADVISOR = 'SERVICE_ADVISOR';
    case WORKSHOP_FOREMAN = 'WORKSHOP_FOREMAN';
    case ADMIN = 'ADMIN';

    public function isStaff(): bool
    {
        return $this !== self::CUSTOMER;
    }

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer',
            self::SERVICE_ADVISOR => 'Service advisor',
            self::WORKSHOP_FOREMAN => 'Workshop foreman',
            self::ADMIN => 'Admin',
        };
    }
}
