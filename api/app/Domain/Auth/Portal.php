<?php

namespace App\Domain\Auth;

enum Portal: string
{
    case CUSTOMER = 'CUSTOMER';
    case STAFF = 'STAFF';
}
