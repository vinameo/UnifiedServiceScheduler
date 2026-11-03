<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class OutsideOpeningHours extends ProblemException
{
    public function __construct(
        ?string $opensAt,
        ?string $closesAt,
        ?string $latestStart,
        int $durationMinutes,
    ) {
        parent::__construct(
            status: 422,
            problemCode: 'OUTSIDE_OPENING_HOURS',
            title: 'The service would end after closing time.',
            extra: [
                'opensAt' => $opensAt,
                'closesAt' => $closesAt,
                'latestStart' => $latestStart,
                'durationMinutes' => $durationMinutes,
            ],
        );
    }
}

