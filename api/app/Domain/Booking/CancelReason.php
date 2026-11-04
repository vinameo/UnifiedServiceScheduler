<?php

namespace App\Domain\Booking;

enum CancelReason: string
{
    case CUSTOMER_REQUEST = 'CUSTOMER_REQUEST';
    case CUSTOMER_BUSY = 'CUSTOMER_BUSY';
    case DONE_ELSEWHERE = 'DONE_ELSEWHERE';
    case DEALERSHIP_ISSUE = 'DEALERSHIP_ISSUE';
    case OTHER = 'OTHER';
    case SOMETHING_CAME_UP = 'SOMETHING_CAME_UP';

    /**
     * @return list<self>
     */
    public static function forStaff(): array
    {
        return [
            self::CUSTOMER_REQUEST,
            self::CUSTOMER_BUSY,
            self::DONE_ELSEWHERE,
            self::DEALERSHIP_ISSUE,
            self::OTHER,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forCustomer(): array
    {
        return [
            self::SOMETHING_CAME_UP,
            self::DONE_ELSEWHERE,
            self::OTHER,
        ];
    }
}

