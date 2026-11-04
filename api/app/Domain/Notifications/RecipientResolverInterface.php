<?php

namespace App\Domain\Notifications;

use App\Models\OutboxMessage;

interface RecipientResolverInterface
{
    /**
     * Resolves the customer and user contact information at send time (D-11.4).
     * Returns null if the recipient or account cannot be resolved.
     */
    public function resolve(OutboxMessage $message): ?Recipient;
}
