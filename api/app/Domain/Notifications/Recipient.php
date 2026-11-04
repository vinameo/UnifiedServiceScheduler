<?php

namespace App\Domain\Notifications;

readonly class Recipient
{
    public function __construct(
        public ?string $phone,
        public ?string $email,
        public ?string $name = null,
        public ?string $customerId = null,
        public ?int $userId = null,
    ) {}
}
