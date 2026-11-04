<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AssertsNoOverlap
{
    protected function setUpAssertsNoOverlap(): void
    {
        $this->beforeApplicationDestroyed(function (): void {
            $this->assertNoOverlappingAllocations();
        });
    }

    protected function tearDownAssertsNoOverlap(): void
    {
        $this->assertNoOverlappingAllocations();
    }

    public function assertNoOverlappingAllocations(): void
    {
        if (! Schema::hasTable('resource_allocations')) {
            return;
        }

        /** @var list<object{a_id: string, b_id: string, resource_type: string, resource_id: string}> $overlaps */
        $overlaps = DB::select(<<<'SQL'
            SELECT a.id AS a_id, b.id AS b_id, a.resource_type, a.resource_id
            FROM resource_allocations a
            JOIN resource_allocations b
              ON a.resource_type = b.resource_type
             AND a.resource_id = b.resource_id
             AND a.id < b.id
             AND a.active
             AND b.active
             AND a.period && b.period
        SQL);

        $this->assertEmpty($overlaps, 'Found overlapping active resource allocations: '.json_encode($overlaps));
    }

    public function assertM042Passes(): void
    {
        if (! Schema::hasTable('appointments') || ! Schema::hasTable('resource_allocations')) {
            return;
        }

        /** @var list<object{id: string}> $invalid */
        $invalid = DB::select(<<<'SQL'
            SELECT a.id
            FROM appointments a
            LEFT JOIN resource_allocations r ON r.appointment_id = a.id AND r.active
            GROUP BY a.id, a.status, a.vehicle_id, a.contact_required_reason
            HAVING (
                a.status IN ('HELD','CONFIRMED','CHECKED_IN','IN_PROGRESS')
                AND NOT (a.status = 'CONFIRMED' AND a.contact_required_reason = 'OVERRUN')
                AND count(r.id) <> 2 + (a.vehicle_id IS NOT NULL)::int
            )
            OR (
                (a.status NOT IN ('HELD','CONFIRMED','CHECKED_IN','IN_PROGRESS') OR (a.status = 'CONFIRMED' AND a.contact_required_reason = 'OVERRUN'))
                AND count(r.id) > 0
            )
        SQL);

        $this->assertEmpty($invalid, 'Found appointments with invalid allocation count per M-04.2: '.json_encode($invalid));
    }
}
