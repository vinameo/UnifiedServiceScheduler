<?php

namespace App\Domain\MasterData\Services;

use Carbon\CarbonImmutable;

interface UpcomingAssignmentsReader
{
    public function count(string $technicianId, CarbonImmutable $now): int;

    /**
     * @return list<array{id: string, reference: string, start: string, end: string, customerName: string}>
     */
    public function preview(string $technicianId, CarbonImmutable $now, int $limit = 20): array;
}
