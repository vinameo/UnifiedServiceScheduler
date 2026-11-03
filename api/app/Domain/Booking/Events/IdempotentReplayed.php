<?php

namespace App\Domain\Booking\Events;

use Illuminate\Foundation\Events\Dispatchable;

class IdempotentReplayed
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $key,
    ) {}
}
