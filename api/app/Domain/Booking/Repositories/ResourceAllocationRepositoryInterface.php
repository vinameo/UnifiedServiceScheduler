<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\BusyInterval;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\ResourceType;
use Carbon\CarbonImmutable;

interface ResourceAllocationRepositoryInterface
{
    /**
     * @return list<BusyInterval>
     */
    public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array;

    /**
     * @param  list<array{resource_type: ResourceType|string, resource_id: string, start_at: CarbonImmutable|string, end_at: CarbonImmutable|string}>  $allocations
     */
    public function insertFor(string $appointmentId, string $dealershipId, array $allocations, CarbonImmutable $now): void;

    public function deactivateFor(string $appointmentId, CarbonImmutable $now): int;

    public function deactivateType(string $appointmentId, ResourceType|string $resourceType, CarbonImmutable $now): int;

    /**
     * @return list<object{appointment_id: string, reference: string, start_at: string, end_at: string}>
     */
    public function upcomingForTechnician(string $technicianId, CarbonImmutable $now): array;
}
