<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\BusyInterval;
use App\Domain\Allocation\Contracts\BusyIntervalReader;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\ResourceType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourceAllocationRepository implements BusyIntervalReader, ResourceAllocationRepositoryInterface
{
    /**
     * @return list<BusyInterval>
     */
    public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array
    {
        $windowStart = $window->start->toIso8601String();
        $windowEnd = $window->end->toIso8601String();
        $nowStr = $now->toIso8601String();

        /** @var list<object{appointment_id: string, resource_type: string, resource_id: string, start_at: string, end_at: string}> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT
                ra.appointment_id,
                ra.resource_type,
                ra.resource_id,
                lower(ra.period) as start_at,
                upper(ra.period) as end_at
            FROM resource_allocations ra
            JOIN appointments a ON a.id = ra.appointment_id
            WHERE ra.dealership_id = :dealershipId
              AND ra.active = true
              AND ra.period && tstzrange(:windowStart, :windowEnd, '[)')
              AND (a.status <> 'HELD' OR a.expires_at > :now)
        SQL, [
            'dealershipId' => $dealershipId,
            'windowStart' => $windowStart,
            'windowEnd' => $windowEnd,
            'now' => $nowStr,
        ]);

        $intervals = [];
        foreach ($rows as $row) {
            $intervals[] = new BusyInterval(
                range: new TimeRange(
                    CarbonImmutable::parse($row->start_at),
                    CarbonImmutable::parse($row->end_at),
                ),
                appointmentId: $row->appointment_id,
                resourceType: $row->resource_type,
                resourceId: $row->resource_id,
            );
        }

        return $intervals;
    }

    /**
     * @param  list<array{resource_type: ResourceType|string, resource_id: string, start_at: CarbonImmutable|string, end_at: CarbonImmutable|string}>  $allocations
     */
    public function insertFor(string $appointmentId, string $dealershipId, array $allocations, CarbonImmutable $now): void
    {
        foreach ($allocations as $alloc) {
            $id = (string) Str::uuid();
            $resourceType = $alloc['resource_type'] instanceof ResourceType ? $alloc['resource_type']->value : (string) $alloc['resource_type'];
            $resourceId = (string) $alloc['resource_id'];
            $start = $alloc['start_at'] instanceof CarbonImmutable ? $alloc['start_at']->toIso8601String() : (string) $alloc['start_at'];
            $end = $alloc['end_at'] instanceof CarbonImmutable ? $alloc['end_at']->toIso8601String() : (string) $alloc['end_at'];

            DB::statement(<<<'SQL'
                INSERT INTO resource_allocations (
                    id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                ) VALUES (
                    :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
                )
            SQL, [
                'id' => $id,
                'appointment_id' => $appointmentId,
                'dealership_id' => $dealershipId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'start' => $start,
                'end' => $end,
                'created_at' => $now->toIso8601String(),
            ]);
        }
    }

    public function deactivateFor(string $appointmentId, CarbonImmutable $now): int
    {
        return DB::table('resource_allocations')
            ->where('appointment_id', $appointmentId)
            ->where('active', true)
            ->update([
                'active' => false,
                'deactivated_at' => $now,
            ]);
    }

    public function deactivateType(string $appointmentId, ResourceType|string $resourceType, CarbonImmutable $now): int
    {
        $type = $resourceType instanceof ResourceType ? $resourceType->value : (string) $resourceType;

        return DB::table('resource_allocations')
            ->where('appointment_id', $appointmentId)
            ->where('resource_type', $type)
            ->where('active', true)
            ->update([
                'active' => false,
                'deactivated_at' => $now,
            ]);
    }

    /**
     * @return list<object{appointment_id: string, reference: string, start_at: string, end_at: string}>
     */
    public function upcomingForTechnician(string $technicianId, CarbonImmutable $now): array
    {
        /** @var list<object{appointment_id: string, reference: string, start_at: string, end_at: string}> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT
                ra.appointment_id,
                a.reference,
                lower(ra.period) as start_at,
                upper(ra.period) as end_at
            FROM resource_allocations ra
            JOIN appointments a ON a.id = ra.appointment_id
            WHERE ra.resource_type = 'TECHNICIAN'
              AND ra.resource_id = :technicianId
              AND ra.active = true
              AND upper(ra.period) > :now
              AND (a.status <> 'HELD' OR a.expires_at > :now)
            ORDER BY lower(ra.period) ASC
        SQL, [
            'technicianId' => $technicianId,
            'now' => $now->toIso8601String(),
        ]);

        return $rows;
    }
}
