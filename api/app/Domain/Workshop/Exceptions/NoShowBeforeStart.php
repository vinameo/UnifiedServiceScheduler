<?php

namespace App\Domain\Workshop\Exceptions;

use App\Support\Problem\ProblemException;
use Carbon\CarbonImmutable;

class NoShowBeforeStart extends ProblemException
{
    public function __construct(?CarbonImmutable $startAt = null, ?CarbonImmutable $now = null)
    {
        $detail = ($startAt !== null && $now !== null)
            ? "You can mark a no-show only after the start time ({$startAt->toIso8601String()}). Current time is {$now->toIso8601String()}."
            : 'You can mark a no-show only after the start time.';

        parent::__construct(
            status: 422,
            problemCode: 'NO_SHOW_BEFORE_START',
            title: 'You can mark a no-show only after the start time.',
            detail: $detail,
            extra: array_filter([
                'startAt' => $startAt?->toIso8601String(),
                'now' => $now?->toIso8601String(),
            ]),
        );
    }
}
