<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidateFinder;
use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\ResourceType;
use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

class ResourceSnapshotTest extends TestCase
{
    public function test_with_busy_returns_new_instance_without_mutating_original(): void
    {
        $tech = new TechnicianState('tech-1', 'An', Level::from(2), true, []);
        $bay = new BayState('bay-1', 'K1', BayType::QUICK, true, []);

        $snapshot = new ResourceSnapshot(
            dealershipId: 'dealership-1',
            timezone: 'Asia/Ho_Chi_Minh',
            localDate: '2026-11-04',
            technicians: [$tech],
            bays: [$bay],
        );

        $range = TimeRange::of('2026-11-04 02:00:00', '2026-11-04 03:00:00');
        $updated = $snapshot->withBusy('TECHNICIAN', 'tech-1', $range);

        $this->assertNotSame($snapshot, $updated);
        $this->assertCount(0, $snapshot->technicians[0]->busy);
        $this->assertCount(1, $updated->technicians[0]->busy);
        $this->assertSame($range->start->toIso8601String(), $updated->technicians[0]->busy[0]->range->start->toIso8601String());
        $this->assertSame($range->end->toIso8601String(), $updated->technicians[0]->busy[0]->range->end->toIso8601String());
    }

    public function test_with_busy_technician_with_enum_and_string(): void
    {
        $tech1 = new TechnicianState('tech-1', 'An', Level::from(2), true, []);
        $tech2 = new TechnicianState('tech-2', 'Bình', Level::from(3), true, []);

        $snapshot = new ResourceSnapshot(
            dealershipId: 'dealership-1',
            timezone: 'Asia/Ho_Chi_Minh',
            localDate: '2026-11-04',
            technicians: [$tech1, $tech2],
            bays: [],
        );

        $range1 = TimeRange::of('2026-11-04 02:00:00', '2026-11-04 03:00:00');
        $range2 = TimeRange::of('2026-11-04 03:00:00', '2026-11-04 04:00:00');

        $updatedWithEnum = $snapshot->withBusy(ResourceType::TECHNICIAN, 'tech-1', $range1, 'apt-1');
        $updatedWithString = $updatedWithEnum->withBusy('technician', 'tech-1', $range2);

        $this->assertCount(2, $updatedWithString->technicians[0]->busy);
        $this->assertSame('apt-1', $updatedWithString->technicians[0]->busy[0]->appointmentId);
        $this->assertNull($updatedWithString->technicians[0]->busy[1]->appointmentId);
        // tech-2 remains unchanged
        $this->assertCount(0, $updatedWithString->technicians[1]->busy);
    }

    public function test_with_busy_bay(): void
    {
        $bay1 = new BayState('bay-1', 'K1', BayType::QUICK, true, []);
        $bay2 = new BayState('bay-2', 'K2', BayType::STANDARD, true, []);

        $snapshot = new ResourceSnapshot(
            dealershipId: 'dealership-1',
            timezone: 'Asia/Ho_Chi_Minh',
            localDate: '2026-11-04',
            technicians: [],
            bays: [$bay1, $bay2],
        );

        $range = TimeRange::of('2026-11-04 02:00:00', '2026-11-04 03:00:00');
        $updated = $snapshot->withBusy(ResourceType::BAY, 'bay-1', $range, 'apt-1');

        $this->assertCount(1, $updated->bays[0]->busy);
        $this->assertSame('apt-1', $updated->bays[0]->busy[0]->appointmentId);
        $this->assertCount(0, $updated->bays[1]->busy);
    }

    public function test_with_busy_unsupported_resource_type_throws(): void
    {
        $snapshot = new ResourceSnapshot(
            dealershipId: 'dealership-1',
            timezone: 'Asia/Ho_Chi_Minh',
            localDate: '2026-11-04',
        );

        $range = TimeRange::of('2026-11-04 02:00:00', '2026-11-04 03:00:00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported resource type for withBusy: VEHICLE');

        $snapshot->withBusy('VEHICLE', 'v-1', $range);
    }

    public function test_with_busy_blocks_candidate_finder_allocation(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->bay('K1', BayType::QUICK)
            ->build();

        $tech = $snapshot->technicians[0];
        $request = SnapshotBuilder::request('OIL', '10:00-11:00');

        $finder = new CandidateFinder;
        $resultBefore = $finder->find($request, $snapshot);
        $this->assertTrue($resultBefore->found());

        // Now extend/mark An as busy over the requested time
        $busyRange = TimeRange::of(
            $request->range->start,
            $request->range->end,
        );
        $snapshotAfter = $snapshot->withBusy('TECHNICIAN', $tech->id, $busyRange);

        $resultAfter = $finder->find($request, $snapshotAfter);
        $this->assertFalse($resultAfter->found());
    }
}
