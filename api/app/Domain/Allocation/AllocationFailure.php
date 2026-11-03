<?php

namespace App\Domain\Allocation;

enum AllocationFailure: string
{
    case NO_QUALIFIED_TECHNICIAN = 'NO_QUALIFIED_TECHNICIAN';
    case NO_TECHNICIAN_AVAILABLE = 'NO_TECHNICIAN_AVAILABLE';
    case NO_BAY_AVAILABLE = 'NO_BAY_AVAILABLE';
}
