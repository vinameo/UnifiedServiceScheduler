<?php

namespace App\Domain\Booking;

enum ResourceType: string
{
    case TECHNICIAN = 'TECHNICIAN';
    case BAY = 'BAY';
    case VEHICLE = 'VEHICLE';
}
