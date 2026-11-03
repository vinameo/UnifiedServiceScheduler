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
}
