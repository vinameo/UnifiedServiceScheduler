<?php

namespace App\Domain\Notifications\Exceptions;

use RuntimeException;
use Throwable;

class DeliveryFailed extends RuntimeException
{
    public function __construct(string $message = 'Delivery failed', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
