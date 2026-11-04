<?php

namespace App\Domain\Notifications\Exceptions;

use App\Support\Problem\ProblemException;

class OutboxMessageNotFound extends ProblemException
{
    public function __construct(?string $id = null)
    {
        parent::__construct(
            status: 404,
            problemCode: 'OUTBOX_MESSAGE_NOT_FOUND',
            title: 'Message not found.',
            detail: $id !== null && $id !== '' ? "Outbox message {$id} was not found." : null,
        );
    }
}
