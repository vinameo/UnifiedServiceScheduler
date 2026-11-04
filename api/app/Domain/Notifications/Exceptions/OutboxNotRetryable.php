<?php

namespace App\Domain\Notifications\Exceptions;

use App\Support\Problem\ProblemException;

class OutboxNotRetryable extends ProblemException
{
    public function __construct(?string $detail = null)
    {
        parent::__construct(
            status: 422,
            problemCode: 'OUTBOX_NOT_RETRYABLE',
            title: 'Only failed messages can be sent again.',
            detail: $detail,
        );
    }
}
