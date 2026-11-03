<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidateFinder;
use App\Domain\Allocation\LowestLevelFirstStrategy;
use App\Domain\Allocation\TechnicianState;
use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

class LowestLevelFirstStrategyTest extends TestCase
{
    /**
     * TC-03-07: AC-03.7
     * Given An (2) and Em (6), both free, 0 booked minutes;
     * when Search OIL (level 1);
     * then First pair is An.
     */
    public function test_AC_03_7_lowest_level_ranks_first(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('Em', level: 6)
            ->technician('An', level: 2)
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '10:00-11:00');

        $strategy = new LowestLevelFirstStrategy;
        $ranked = $strategy->rankTechnicians($snapshot->technicians, $request, $snapshot);

        $this->assertCount(2, $ranked);
        $this->assertSame('An', $ranked[0]->name);
        $this->assertSame('Em', $ranked[1]->name);

        $finder = new CandidateFinder($strategy);
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertNotEmpty($result->pairs);
        $this->assertSame('An', $result->pairs[0]->technician->name);
    }

    /**
     * TC-03-09: AC-03.9
     * Given Dũng and Chi both level 3; Dũng booked 120 min that day, Chi 60;
     * when Search OIL 15:00–16:00;
     * then Chi before Dũng.
     */
    public function test_AC_03_9_fewer_booked_minutes_ranks_first(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('Dũng', level: 3)
            ->technician('Chi', level: 3)
            ->busy('Dũng', '09:00-11:00') // 120 min
            ->busy('Chi', '09:00-10:00')   // 60 min
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '15:00-16:00');

        $strategy = new LowestLevelFirstStrategy;
        $ranked = $strategy->rankTechnicians($snapshot->technicians, $request, $snapshot);

        $this->assertCount(2, $ranked);
        $this->assertSame('Chi', $ranked[0]->name);
        $this->assertSame('Dũng', $ranked[1]->name);

        $finder = new CandidateFinder($strategy);
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertSame('Chi', $result->pairs[0]->technician->name);
        $this->assertSame('Dũng', $result->pairs[1]->technician->name);
    }

    /**
     * TC-03-10: AC-03.9, INV-03.3
     * Given Two technicians identical except id;
     * when Search twice, second time with the input array reversed;
     * then Equal results; smaller id (strcmp) first.
     */
    public function test_INV_03_3_same_input_same_order(): void
    {
        $idA = '00000000-0000-0000-0000-000000000001';
        $idB = '00000000-0000-0000-0000-000000000002';

        $techA = new TechnicianState(id: $idA, name: 'TechA', level: Level::from(3));
        $techB = new TechnicianState(id: $idB, name: 'TechB', level: Level::from(3));

        $snapshotForward = SnapshotBuilder::day('2026-11-02')
            ->technician('TechA', level: 3, id: $idA)
            ->technician('TechB', level: 3, id: $idB)
            ->bay('K1', BayType::QUICK)
            ->build();

        $snapshotReversed = SnapshotBuilder::day('2026-11-02')
            ->technician('TechB', level: 3, id: $idB)
            ->technician('TechA', level: 3, id: $idA)
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '10:00-11:00');

        $strategy = new LowestLevelFirstStrategy;

        $ranked1 = $strategy->rankTechnicians([$techA, $techB], $request, $snapshotForward);
        $ranked2 = $strategy->rankTechnicians([$techB, $techA], $request, $snapshotReversed);

        $this->assertSame($idA, $ranked1[0]->id);
        $this->assertSame($idB, $ranked1[1]->id);

        $this->assertSame($idA, $ranked2[0]->id);
        $this->assertSame($idB, $ranked2[1]->id);

        $finder = new CandidateFinder($strategy);
        $result1 = $finder->find($request, $snapshotForward);
        $result2 = $finder->find($request, $snapshotReversed);

        $this->assertSame($result1->pairs[0]->technician->id, $result2->pairs[0]->technician->id);
        $this->assertSame($idA, $result1->pairs[0]->technician->id);
    }

    public function test_rank_bays_by_code_then_id(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->bay('K2', BayType::QUICK, id: '00000000-0000-0000-0000-000000000002')
            ->bay('K1', BayType::QUICK, id: '00000000-0000-0000-0000-000000000001')
            ->build();

        $request = SnapshotBuilder::request('OIL', '10:00-11:00');

        $strategy = new LowestLevelFirstStrategy;
        $ranked = $strategy->rankBays($snapshot->bays, $request);

        $this->assertSame('K1', $ranked[0]->code);
        $this->assertSame('K2', $ranked[1]->code);

        // Ties in code broken by id (strcmp)
        $baySameCode1 = $snapshot->bays[0]; // id ending in 2
        $baySameCode2 = $snapshot->bays[1]; // id ending in 1
        // Modify code to match
        $b1 = new BayState(
            id: '00000000-0000-0000-0000-000000000002',
            code: 'SAME',
            bayType: BayType::QUICK,
        );
        $b2 = new BayState(
            id: '00000000-0000-0000-0000-000000000001',
            code: 'SAME',
            bayType: BayType::QUICK,
        );

        $rankedTies = $strategy->rankBays([$b1, $b2], $request);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $rankedTies[0]->id);
        $this->assertSame('00000000-0000-0000-0000-000000000002', $rankedTies[1]->id);
    }

    public function test_prefer_technician_and_bay_moves_to_front(): void
    {
        $id1 = '00000000-0000-0000-0000-000000000001';
        $id2 = '00000000-0000-0000-0000-000000000002';
        $id3 = '00000000-0000-0000-0000-000000000003';

        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('T1', level: 1, id: $id1)
            ->technician('T2', level: 2, id: $id2)
            ->technician('T3', level: 3, id: $id3)
            ->bay('B1', BayType::QUICK, id: $id1)
            ->bay('B2', BayType::QUICK, id: $id2)
            ->bay('B3', BayType::QUICK, id: $id3)
            ->build();

        $strategy = new LowestLevelFirstStrategy;

        // Prefer T3 moves T3 to index 0, keeping T1 and T2 in their relative sorted order
        $requestTech = SnapshotBuilder::request('OIL', '10:00-11:00', preferTechnicianId: $id3);
        $rankedTechs = $strategy->rankTechnicians($snapshot->technicians, $requestTech, $snapshot);
        $this->assertSame($id3, $rankedTechs[0]->id);
        $this->assertSame($id1, $rankedTechs[1]->id);
        $this->assertSame($id2, $rankedTechs[2]->id);

        // Prefer B3 moves B3 to index 0, keeping B1 and B2 in their relative sorted order
        $requestBay = SnapshotBuilder::request('OIL', '10:00-11:00', preferBayId: $id3);
        $rankedBays = $strategy->rankBays($snapshot->bays, $requestBay);
        $this->assertSame($id3, $rankedBays[0]->id);
        $this->assertSame($id1, $rankedBays[1]->id);
        $this->assertSame($id2, $rankedBays[2]->id);
    }

    public function test_unknown_preference_leaves_order_unchanged(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('T1', level: 1)
            ->technician('T2', level: 2)
            ->bay('B1', BayType::QUICK)
            ->bay('B2', BayType::QUICK)
            ->build();

        $strategy = new LowestLevelFirstStrategy;
        $request = SnapshotBuilder::request(
            'OIL',
            '10:00-11:00',
            preferTechnicianId: '00000000-0000-0000-0000-000000000999',
            preferBayId: '00000000-0000-0000-0000-000000000999',
        );

        $rankedTechs = $strategy->rankTechnicians($snapshot->technicians, $request, $snapshot);
        $this->assertSame('T1', $rankedTechs[0]->name);
        $this->assertSame('T2', $rankedTechs[1]->name);

        $rankedBays = $strategy->rankBays($snapshot->bays, $request);
        $this->assertSame('B1', $rankedBays[0]->code);
        $this->assertSame('B2', $rankedBays[1]->code);
    }
}
