<?php

namespace App\Domain\Monitoring\Services;

class RequestMetrics
{
    private int $allocationRetries = 0;

    private bool $replayed = false;

    private bool $appointmentModified = false;

    public function recordAllocationRetry(int $count = 1): void
    {
        $this->allocationRetries += $count;
    }

    public function getAllocationRetriesCount(): int
    {
        return $this->allocationRetries;
    }

    public function recordReplay(): void
    {
        $this->replayed = true;
    }

    public function hasReplayed(): bool
    {
        return $this->replayed;
    }

    public function recordAppointmentModified(): void
    {
        $this->appointmentModified = true;
    }

    public function hasAppointmentModified(): bool
    {
        return $this->appointmentModified;
    }

    public function reset(): void
    {
        $this->allocationRetries = 0;
        $this->replayed = false;
        $this->appointmentModified = false;
    }
}
