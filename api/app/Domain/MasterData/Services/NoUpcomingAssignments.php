<?php

namespace App\Domain\MasterData\Services;

use Carbon\CarbonImmutable;

class NoUpcomingAssignments implements UpcomingAssignmentsReader
{
    public function count(string $technicianId, CarbonImmutable $now): int
    {
        return 0;
    }

    /**
     * @return list<array{id: string, reference: string, start: string, end: string, customerName: string}>
     */
    public function preview(string $technicianId, CarbonImmutable $now, int $limit = 20): array
    {
        return [];
    }
}
