<?php

namespace App\Domain\Workshop\Exceptions;

use App\Support\Problem\ProblemException;

class NotServiceDay extends ProblemException
{
    public function __construct(string $serviceDate = '', string $currentDate = '', string $timezone = '')
    {
        $detail = ($serviceDate !== '' && $currentDate !== '')
            ? "Check-in is only allowed on the scheduled service day ({$serviceDate} in {$timezone}). Today is {$currentDate}."
            : 'Check-in is only allowed on the scheduled service day.';

        parent::__construct(
            status: 422,
            problemCode: 'NOT_SERVICE_DAY',
            title: 'Check-in is only allowed on the scheduled service day.',
            detail: $detail,
            extra: array_filter([
                'serviceDate' => $serviceDate ?: null,
                'currentDate' => $currentDate ?: null,
                'timezone' => $timezone ?: null,
            ]),
        );
    }
}
