<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\AllocationFailure;
use App\Domain\Allocation\CandidateFinder;
use App\Domain\Allocation\TimeRange;
use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

class CandidateFinderTest extends TestCase
{
    /**
     * TC-03-01: AC-03.1
     * Given An (level 2) busy 09:00–10:00; Bay K1 free;
     * when Search OIL 10:00–11:00;
     * then First pair has An.
     */
    public function test_AC_03_1_back_to_back_is_not_an_overlap(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->busy('An', '09:00-10:00')
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '10:00-11:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertNotEmpty($result->pairs);
        $this->assertSame('An', $result->pairs[0]->technician->name);
        $this->assertSame('K1', $result->pairs[0]->bay->code);
    }

    /**
     * TC-03-02: AC-03.2
     * Given An busy 09:00–10:00;
     * when Search 09:30–10:30;
     * then No pair has An.
     */
    public function test_AC_03_2_partial_overlap_removes_technician(): void
    {
        $snapshotWithBoth = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->busy('An', '09:00-10:00')
            ->technician('Bình', level: 2)
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:30-10:30');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshotWithBoth);

        $this->assertTrue($result->found());
        $technicianNames = array_map(fn ($pair) => $pair->technician->name, $result->pairs);
        $this->assertNotContains('An', $technicianNames);
        $this->assertContains('Bình', $technicianNames);

        // When An is the only qualified technician, the result has no candidate pairs
        $snapshotOnlyAn = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->busy('An', '09:00-10:00')
            ->bay('K1', BayType::QUICK)
            ->build();

        $resultOnlyAn = $finder->find($request, $snapshotOnlyAn);
        $this->assertFalse($resultOnlyAn->found());
        $this->assertEmpty($resultOnlyAn->pairs);
    }

    /**
     * TC-03-03: AC-03.3
     * Given An busy 08:00–12:00;
     * when Search 09:00–10:00;
     * then An removed.
     */
    public function test_AC_03_3_range_inside_busy_removes_technician(): void
    {
        $snapshotWithBoth = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->busy('An', '08:00-12:00')
            ->technician('Bình', level: 2)
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshotWithBoth);

        $this->assertTrue($result->found());
        $technicianNames = array_map(fn ($pair) => $pair->technician->name, $result->pairs);
        $this->assertNotContains('An', $technicianNames);
        $this->assertContains('Bình', $technicianNames);

        // When An is the only qualified technician, the result has no candidate pairs
        $snapshotOnlyAn = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->busy('An', '08:00-12:00')
            ->bay('K1', BayType::QUICK)
            ->build();

        $resultOnlyAn = $finder->find($request, $snapshotOnlyAn);
        $this->assertFalse($resultOnlyAn->found());
        $this->assertEmpty($resultOnlyAn->pairs);
    }

    /**
     * TC-03-04: AC-03.4
     * Given An (level 2), Dũng (level 3), the only technicians, both busy 09–10; K1 free;
     * when Search 09:00–10:00;
     * then pairs = [], failure = NO_TECHNICIAN_AVAILABLE.
     */
    public function test_AC_03_4_all_qualified_busy_gives_no_technician_available(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->technician('Dũng', level: 3)
            ->busy('An', '09:00-10:00')
            ->busy('Dũng', '09:00-10:00')
            ->bay('K1', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_TECHNICIAN_AVAILABLE, $result->failure);
    }

    /**
     * TC-03-05: AC-03.5
     * Given An free; K1, K2 busy 09–10;
     * when Search OIL 09:00–10:00;
     * then NO_BAY_AVAILABLE.
     */
    public function test_AC_03_5_all_bays_busy_gives_no_bay_available(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->bay('K1', BayType::QUICK)
            ->bay('K2', BayType::QUICK)
            ->busy('K1', '09:00-10:00')
            ->busy('K2', '09:00-10:00')
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_BAY_AVAILABLE, $result->failure);
    }

    /**
     * TC-03-06: AC-03.6, D-03.10
     * Given An (2), Dũng (3), Em (6), all free;
     * when Search MAINT_20K (level 3);
     * then Candidates Dũng and Em; An never in a pair.
     */
    public function test_D_03_10_level_at_or_above_qualifies(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->technician('Dũng', level: 3)
            ->technician('Em', level: 6)
            ->bay('S1', BayType::STANDARD)
            ->build();

        $request = SnapshotBuilder::request('MAINT_20K', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $technicianNames = array_map(fn ($pair) => $pair->technician->name, $result->pairs);
        $this->assertContains('Dũng', $technicianNames);
        $this->assertContains('Em', $technicianNames);
        $this->assertNotContains('An', $technicianNames);
    }

    /**
     * TC-03-08: AC-03.8
     * Given An (2), Dũng (3), Chi (4), all free; KEV free;
     * when Search EV_BATTERY (level 5);
     * then NO_QUALIFIED_TECHNICIAN.
     */
    public function test_AC_03_8_no_high_enough_level_gives_no_qualified_technician(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->technician('Dũng', level: 3)
            ->technician('Chi', level: 4)
            ->bay('KEV', BayType::EV)
            ->build();

        $request = SnapshotBuilder::request('EV_BATTERY', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_QUALIFIED_TECHNICIAN, $result->failure);
    }

    /**
     * TC-03-12: AC-03.10
     * Given Em (level 6, the only one ≥ 5) active=false; KEV free;
     * when Search EV_BATTERY;
     * then NO_QUALIFIED_TECHNICIAN.
     */
    public function test_AC_03_10_inactive_technician_is_not_qualified(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('Em', level: 6, active: false)
            ->bay('KEV', BayType::EV)
            ->build();

        $request = SnapshotBuilder::request('EV_BATTERY', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_QUALIFIED_TECHNICIAN, $result->failure);
    }

    /**
     * TC-03-13: AC-03.11
     * Given KEV active=false; Em free;
     * when Search EV_BATTERY;
     * then NO_BAY_AVAILABLE.
     */
    public function test_AC_03_11_inactive_bay_is_not_available(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('Em', level: 6)
            ->bay('KEV', BayType::EV, active: false)
            ->build();

        $request = SnapshotBuilder::request('EV_BATTERY', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_BAY_AVAILABLE, $result->failure);
    }

    /**
     * TC-03-14: D-03.10
     * Given Only Chi (level 4), free;
     * when Search a level-4 service;
     * then Chi is a candidate.
     */
    public function test_D_03_10_equal_level_qualifies(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('Chi', level: 4)
            ->bay('S1', BayType::STANDARD)
            ->build();

        $request = SnapshotBuilder::request(Level::from(4), '09:00-10:00', BayType::STANDARD);

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertNotEmpty($result->pairs);
        $this->assertSame('Chi', $result->pairs[0]->technician->name);
    }

    /**
     * TC-03-15: D-03.10, D-03.5
     * Given An (2) free; Em (6) busy;
     * when Search MAINT_20K (level 3);
     * then NO_TECHNICIAN_AVAILABLE (Em qualifies but is busy; An does not qualify).
     */
    public function test_D_03_10_free_but_too_junior_is_not_used(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->technician('Em', level: 6)
            ->busy('Em', '09:00-10:00')
            ->bay('S1', BayType::STANDARD)
            ->build();

        $request = SnapshotBuilder::request('MAINT_20K', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_TECHNICIAN_AVAILABLE, $result->failure);
    }

    /**
     * TC-03-16: D-03.5
     * Given Every technician and bay busy;
     * when Search;
     * then NO_TECHNICIAN_AVAILABLE.
     */
    public function test_D_03_5_both_busy_reports_technician_first(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->busy('An', '09:00-10:00')
            ->bay('K1', BayType::QUICK)
            ->busy('K1', '09:00-10:00')
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00');

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertFalse($result->found());
        $this->assertSame([], $result->pairs);
        $this->assertSame(AllocationFailure::NO_TECHNICIAN_AVAILABLE, $result->failure);
    }

    /**
     * TC-03-24: D-03.2, D-03.3, BR-03.2
     * Given 3 technicians T1<T2<T3, 2 bays K1<K2, all free;
     * when Search;
     * then Exactly 5 pairs: (T1,K1),(T1,K2),(T2,K1),(T2,K2),(T3,K1).
     */
    public function test_D_03_3_pairs_order_and_limit(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('T1', level: 1)
            ->technician('T2', level: 2)
            ->technician('T3', level: 3)
            ->bay('K1', BayType::QUICK)
            ->bay('K2', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00', maxPairs: 5);

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertCount(5, $result->pairs);

        $expected = [
            ['T1', 'K1'],
            ['T1', 'K2'],
            ['T2', 'K1'],
            ['T2', 'K2'],
            ['T3', 'K1'],
        ];

        foreach ($expected as $idx => [$expectedTech, $expectedBay]) {
            $this->assertSame($expectedTech, $result->pairs[$idx]->technician->name);
            $this->assertSame($expectedBay, $result->pairs[$idx]->bay->code);
        }
    }

    /**
     * TC-03-28: D-03.3
     * Given 2 technicians T1<T2 (both qualify for OIL), 5 QUICK bays B1<…<B5, all free;
     * when Search;
     * then Pairs (T1,B1),(T1,B2),(T2,B1),(T1,B3),(T2,B2); both technicians in the first 5.
     */
    public function test_D_03_3_first_pairs_spread_over_technicians(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('T1', level: 1)
            ->technician('T2', level: 2)
            ->bay('B1', BayType::QUICK)
            ->bay('B2', BayType::QUICK)
            ->bay('B3', BayType::QUICK)
            ->bay('B4', BayType::QUICK)
            ->bay('B5', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00', maxPairs: 5);

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertCount(5, $result->pairs);

        $expected = [
            ['T1', 'B1'],
            ['T1', 'B2'],
            ['T2', 'B1'],
            ['T1', 'B3'],
            ['T2', 'B2'],
        ];

        foreach ($expected as $idx => [$expectedTech, $expectedBay]) {
            $this->assertSame($expectedTech, $result->pairs[$idx]->technician->name);
            $this->assertSame($expectedBay, $result->pairs[$idx]->bay->code);
        }

        $techniciansInPairs = array_unique(array_map(fn ($pair) => $pair->technician->name, $result->pairs));
        $this->assertContains('T1', $techniciansInPairs);
        $this->assertContains('T2', $techniciansInPairs);
    }

    /**
     * TC-03-23: INV-03.4, API §B3
     * When TimeRange(10:00, 10:00); maxPairs: 0; snapshot of another dealership; snapshot of another day;
     * then Each throws InvalidArgumentException.
     *
     * @return array<string, array{Closure}>
     */
    public static function invalidRequestsProvider(): array
    {
        return [
            'TimeRange start equal to end' => [
                fn () => new TimeRange('2026-11-02 10:00:00', '2026-11-02 10:00:00'),
            ],
            'AllocationRequest with maxPairs = 0' => [
                fn () => SnapshotBuilder::request('OIL', '10:00-11:00', maxPairs: 0),
            ],
            'Snapshot of another dealership' => [
                function () {
                    $finder = new CandidateFinder;
                    $request = SnapshotBuilder::request(
                        'OIL',
                        '10:00-11:00',
                        dealershipId: '00000000-0000-0000-0000-000000000001',
                    );
                    $snapshot = SnapshotBuilder::day(
                        '2026-11-02',
                        dealershipId: '00000000-0000-0000-0000-000000000002',
                    )->build();

                    $finder->find($request, $snapshot);
                },
            ],
            'Snapshot of another day' => [
                function () {
                    $finder = new CandidateFinder;
                    $request = SnapshotBuilder::request('OIL', '10:00-11:00', date: '2026-11-02');
                    $snapshot = SnapshotBuilder::day('2026-11-03')->build();

                    $finder->find($request, $snapshot);
                },
            ],
        ];
    }

    #[DataProvider('invalidRequestsProvider')]
    public function test_invalid_requests_throw(Closure $scenario): void
    {
        $this->expectException(InvalidArgumentException::class);
        $scenario();
    }
}
