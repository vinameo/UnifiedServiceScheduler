<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\AllocationFailure;
use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\AllocationStrategy;
use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidateFinder;
use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TechnicianState;
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
     * TC-03-17: AC-03.12, INV-03.6, D-03.8
     * Given Appointment X: An + K2 10:00–11:00; Dũng free with fewer minutes than An;
     * when Search 10:30–11:30, exclude = X, preferTechnicianId = An, preferBayId = K2;
     * then First pair (An, K2).
     */
    public function test_AC_03_12_reschedule_inside_own_slot_keeps_technician(): void
    {
        $appointmentX = '00000000-0000-0000-0000-000000000099';

        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->technician('Dũng', level: 2)
            ->bay('K1', BayType::QUICK)
            ->bay('K2', BayType::QUICK)
            // Appointment X occupies An and K2 from 10:00 to 11:00
            ->busy('An', '10:00-11:00', appointment: $appointmentX)
            // An has an additional busy slot (60 mins), so An has 60 booked minutes (excluding X)
            ->busy('An', '08:00-09:00', appointment: '00000000-0000-0000-0000-000000000001')
            // K2 is occupied by Appointment X from 10:00 to 11:00
            ->busy('K2', '10:00-11:00', appointment: $appointmentX)
            ->build();

        $an = array_values(array_filter($snapshot->technicians, fn ($t) => $t->name === 'An'))[0];
        $k2 = array_values(array_filter($snapshot->bays, fn ($b) => $b->code === 'K2'))[0];

        $request = SnapshotBuilder::request(
            'OIL',
            '10:30-11:30',
            preferTechnicianId: $an->id,
            preferBayId: $k2->id,
            excludeAppointmentId: $appointmentX,
        );

        $finder = new CandidateFinder;
        $result = $finder->find($request, $snapshot);

        $this->assertTrue($result->found());
        $this->assertNotEmpty($result->pairs);
        $this->assertSame('An', $result->pairs[0]->technician->name);
        $this->assertSame('K2', $result->pairs[0]->bay->code);

        // Contrast 1: Without excludeAppointmentId, An and K2 are busy, so Dũng and K1 are matched
        $requestWithoutExclude = SnapshotBuilder::request(
            'OIL',
            '10:30-11:30',
            preferTechnicianId: $an->id,
            preferBayId: $k2->id,
        );
        $resultWithoutExclude = $finder->find($requestWithoutExclude, $snapshot);
        $this->assertTrue($resultWithoutExclude->found());
        $this->assertSame('Dũng', $resultWithoutExclude->pairs[0]->technician->name);
        $this->assertSame('K1', $resultWithoutExclude->pairs[0]->bay->code);

        // Contrast 2: Without preferences, Dũng (fewer minutes) and K1 (alphabetical code) are ranked first
        $requestWithoutPreferences = SnapshotBuilder::request(
            'OIL',
            '10:30-11:30',
            excludeAppointmentId: $appointmentX,
        );
        $resultWithoutPreferences = $finder->find($requestWithoutPreferences, $snapshot);
        $this->assertTrue($resultWithoutPreferences->found());
        $this->assertSame('Dũng', $resultWithoutPreferences->pairs[0]->technician->name);
        $this->assertSame('K1', $resultWithoutPreferences->pairs[0]->bay->code);
    }

    /**
     * TC-03-18: D-03.8
     * Given preferTechnicianId = Phát (inactive);
     * when Search;
     * then Same result as without prefer, no error.
     */
    public function test_D_03_8_unqualified_preference_is_ignored(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('An', level: 2)
            ->technician('Dũng', level: 3)
            ->technician('Phát', level: 2, active: false)
            ->bay('K1', BayType::QUICK)
            ->bay('K2', BayType::QUICK, active: false)
            ->build();

        $phat = array_values(array_filter($snapshot->technicians, fn ($t) => $t->name === 'Phát'))[0];
        $inactiveBay = array_values(array_filter($snapshot->bays, fn ($b) => $b->code === 'K2'))[0];

        $requestBaseline = SnapshotBuilder::request('OIL', '10:00-11:00');

        $finder = new CandidateFinder;
        $baselineResult = $finder->find($requestBaseline, $snapshot);
        $this->assertTrue($baselineResult->found());

        // Preferring inactive technician (Phát) yields the exact same result without error
        $requestWithInactiveTech = SnapshotBuilder::request(
            'OIL',
            '10:00-11:00',
            preferTechnicianId: $phat->id,
        );
        $resultWithInactiveTech = $finder->find($requestWithInactiveTech, $snapshot);

        $this->assertTrue($resultWithInactiveTech->found());
        $this->assertCount(count($baselineResult->pairs), $resultWithInactiveTech->pairs);
        foreach ($baselineResult->pairs as $idx => $pair) {
            $this->assertSame($pair->technician->id, $resultWithInactiveTech->pairs[$idx]->technician->id);
            $this->assertSame($pair->bay->id, $resultWithInactiveTech->pairs[$idx]->bay->id);
        }

        // Also test: non-existent technician ID preference is ignored without error
        $requestWithUnknownTech = SnapshotBuilder::request(
            'OIL',
            '10:00-11:00',
            preferTechnicianId: '00000000-0000-0000-0000-000000000999',
        );
        $resultWithUnknownTech = $finder->find($requestWithUnknownTech, $snapshot);
        $this->assertTrue($resultWithUnknownTech->found());
        $this->assertSame($baselineResult->pairs[0]->technician->id, $resultWithUnknownTech->pairs[0]->technician->id);

        // Also test: inactive bay preference is ignored without error
        $requestWithInactiveBay = SnapshotBuilder::request(
            'OIL',
            '10:00-11:00',
            preferBayId: $inactiveBay->id,
        );
        $resultWithInactiveBay = $finder->find($requestWithInactiveBay, $snapshot);
        $this->assertTrue($resultWithInactiveBay->found());
        $this->assertSame($baselineResult->pairs[0]->bay->id, $resultWithInactiveBay->pairs[0]->bay->id);
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

    /**
     * TC-03-27: INV-03.8, FR-03.5
     * Given A fake strategy that reverses the order;
     * when CandidateFinder with that strategy;
     * then Pair order is reversed too.
     */
    public function test_INV_03_8_ranking_comes_from_the_strategy(): void
    {
        $snapshot = SnapshotBuilder::day('2026-11-02')
            ->technician('T1', level: 1)
            ->technician('T2', level: 2)
            ->bay('K1', BayType::QUICK)
            ->bay('K2', BayType::QUICK)
            ->build();

        $request = SnapshotBuilder::request('OIL', '09:00-10:00', maxPairs: 5);

        // 1. Default strategy ranks T1 before T2 (level 1 < level 2) and K1 before K2 (code 'K1' < 'K2')
        $defaultFinder = new CandidateFinder;
        $defaultResult = $defaultFinder->find($request, $snapshot);

        $this->assertTrue($defaultResult->found());
        $this->assertCount(4, $defaultResult->pairs);
        $this->assertSame('T1', $defaultResult->pairs[0]->technician->name);
        $this->assertSame('K1', $defaultResult->pairs[0]->bay->code);
        $this->assertSame('T1', $defaultResult->pairs[1]->technician->name);
        $this->assertSame('K2', $defaultResult->pairs[1]->bay->code);
        $this->assertSame('T2', $defaultResult->pairs[2]->technician->name);
        $this->assertSame('K1', $defaultResult->pairs[2]->bay->code);
        $this->assertSame('T2', $defaultResult->pairs[3]->technician->name);
        $this->assertSame('K2', $defaultResult->pairs[3]->bay->code);

        // 2. Custom pluggable strategy that reverses the candidate order
        $reverseStrategy = new class implements AllocationStrategy
        {
            /**
             * @param  list<TechnicianState>  $free
             * @return list<TechnicianState>
             */
            public function rankTechnicians(array $free, AllocationRequest $request, ResourceSnapshot $snapshot): array
            {
                return array_reverse($free);
            }

            /**
             * @param  list<BayState>  $free
             * @return list<BayState>
             */
            public function rankBays(array $free, AllocationRequest $request): array
            {
                return array_reverse($free);
            }
        };

        // 3. Injecting the custom strategy into CandidateFinder alters candidate pair ranking
        // without any modifications to CandidateFinder (INV-03.8, FR-03.5)
        $customFinder = new CandidateFinder($reverseStrategy);
        $customResult = $customFinder->find($request, $snapshot);

        $this->assertTrue($customResult->found());
        $this->assertCount(4, $customResult->pairs);
        $this->assertSame('T2', $customResult->pairs[0]->technician->name);
        $this->assertSame('K2', $customResult->pairs[0]->bay->code);
        $this->assertSame('T2', $customResult->pairs[1]->technician->name);
        $this->assertSame('K1', $customResult->pairs[1]->bay->code);
        $this->assertSame('T1', $customResult->pairs[2]->technician->name);
        $this->assertSame('K2', $customResult->pairs[2]->bay->code);
        $this->assertSame('T1', $customResult->pairs[3]->technician->name);
        $this->assertSame('K1', $customResult->pairs[3]->bay->code);
    }
}
