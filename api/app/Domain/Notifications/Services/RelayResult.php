<?php

namespace App\Domain\Notifications\Services;

final readonly class RelayResult
{
    public function __construct(
        public int $claimed = 0,
        public int $sent = 0,
        public int $failed = 0,
        public int $skipped = 0,
    ) {}

    /**
     * @return array{claimed: int, sent: int, failed: int, skipped: int}
     */
    public function toArray(): array
    {
        return [
            'claimed' => $this->claimed,
            'sent' => $this->sent,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
        ];
    }
}
