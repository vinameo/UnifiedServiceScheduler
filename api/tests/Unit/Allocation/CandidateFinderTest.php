<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\AllocationFailure;
use App\Domain\Allocation\CandidateFinder;
use App\Domain\Allocation\TimeRange;
use App\Domain\MasterData\BayType;
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
