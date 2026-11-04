<?php

namespace Tests\Support\Notifications;

use App\Domain\Notifications\Channels\NotificationChannel;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;

class RecordingChannel implements NotificationChannel
{
    /**
     * @var list<array{message: OutboxMessage, recipient: Recipient}>
     */
    public array $sent = [];

    public function send(OutboxMessage $message, Recipient $recipient): void
    {
        $this->sent[] = [
            'message' => $message,
            'recipient' => $recipient,
        ];
    }

    public function count(): int
    {
        return count($this->sent);
    }

    /**
     * @return array{message: OutboxMessage, recipient: Recipient}|null
     */
    public function last(): ?array
    {
        return $this->sent[array_key_last($this->sent)] ?? null;
    }

    public function lastRecipient(): ?Recipient
    {
        $last = $this->last();

        return $last !== null ? $last['recipient'] : null;
    }

    public function lastMessage(): ?OutboxMessage
    {
        $last = $this->last();

        return $last !== null ? $last['message'] : null;
    }
}
