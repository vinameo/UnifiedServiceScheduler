<?php

namespace App\Listeners;

use App\Domain\Booking\Events\AllocationRetried;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Events\IdempotentReplayed;
use App\Domain\Monitoring\Services\RequestMetrics;

final class CollectBookingSignals
{
    public function __construct(
        private readonly RequestMetrics $requestMetrics,
    ) {}

    public function handleAllocationRetried(AllocationRetried $event): void
    {
        $this->requestMetrics->recordAllocationRetry();
    }

    public function handleIdempotentReplayed(IdempotentReplayed $event): void
    {
        $this->requestMetrics->recordReplay();
    }

    public function handleAppointmentModified(AppointmentModifiedDetected $event): void
    {
        $this->requestMetrics->recordAppointmentModified();
    }
}
