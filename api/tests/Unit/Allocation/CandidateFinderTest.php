<?php

namespace Tests\Unit\Allocation;

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
