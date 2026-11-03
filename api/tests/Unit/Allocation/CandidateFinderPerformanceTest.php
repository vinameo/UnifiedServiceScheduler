<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\CandidateFinder;
use App\Domain\MasterData\BayType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

#[Group('performance')]
/**
 * @group performance
 */
class CandidateFinderPerformanceTest extends TestCase
{
    /**
     * TC-03-21 / M-03.1:
     * Given Snapshot of 30 technicians, 20 bays, 200 busy intervals across a day (2026-11-02);
     * when 36 find() calls spaced across the day (08:00...16:45 in 15-minute intervals);
     * then Total time for 36 calls has median < 50 ms across 5 runs.
     */
    public function test_TC_03_21_candidate_finder_36_slots_under_50ms(): void
    {
        $date = '2026-11-02';
        $builder = SnapshotBuilder::day($date);

        // 30 technicians across levels 1 to 5
        for ($i = 1; $i <= 30; $i++) {
            $name = sprintf('Tech-%02d', $i);
            $level = (($i - 1) % 5) + 1;
            $builder->technician($name, level: $level);
        }

        // 20 bays across STANDARD, QUICK, EV
        for ($j = 1; $j <= 20; $j++) {
            $code = sprintf('Bay-%02d', $j);
            $type = match ($j % 3) {
                0 => BayType::EV,
                1 => BayType::QUICK,
                default => BayType::STANDARD,
            };
            $builder->bay($code, bayType: $type);
        }

        // 200 busy intervals across the day:
        // 120 intervals on technicians (4 per technician * 30 = 120)
        for ($i = 1; $i <= 30; $i++) {
            $name = sprintf('Tech-%02d', $i);
            $intervals = [
                ['08:30', '09:15'],
                ['10:30', '11:45'],
                ['13:15', '14:15'],
                ['15:30', '16:30'],
            ];
            foreach ($intervals as $range) {
                $builder->busy($name, "{$range[0]}-{$range[1]}");
            }
        }

        // 80 intervals on bays (4 per bay * 20 = 80)
        for ($j = 1; $j <= 20; $j++) {
            $code = sprintf('Bay-%02d', $j);
            $intervals = [
                ['09:00', '10:00'],
                ['11:00', '12:00'],
                ['13:45', '14:45'],
                ['15:00', '16:00'],
            ];
            foreach ($intervals as $range) {
                $builder->busy($code, "{$range[0]}-{$range[1]}");
            }
        }

        $snapshot = $builder->build();

        // Verify snapshot scale: 30 technicians, 20 bays, 200 busy intervals
        $this->assertCount(30, $snapshot->technicians);
        $this->assertCount(20, $snapshot->bays);

        $totalBusyIntervals = 0;
        foreach ($snapshot->technicians as $tech) {
            $totalBusyIntervals += count($tech->busy);
        }
        foreach ($snapshot->bays as $bay) {
            $totalBusyIntervals += count($bay->busy);
        }
        $this->assertSame(200, $totalBusyIntervals);

        // 36 find() calls spaced across the day (08:00...16:45 in 15-minute intervals)
        $requests = [];
        for ($h = 8; $h <= 16; $h++) {
            for ($m = 0; $m < 60; $m += 15) {
                $startStr = sprintf('%02d:%02d', $h, $m);
                $endH = $h + intdiv($m + 60, 60);
                $endM = ($m + 60) % 60;
                $endStr = sprintf('%02d:%02d', $endH, $endM);
                $requests[] = SnapshotBuilder::request('MAINT_20K', "{$startStr}-{$endStr}", date: $date);
            }
        }
        $this->assertCount(36, $requests);

        $finder = new CandidateFinder;

        // Warm up (JIT / opcode cache / class loading)
        foreach ($requests as $request) {
            $finder->find($request, $snapshot);
        }

        // Execute 5 runs, measuring total time for all 36 calls in each run
        $runs = 5;
        $runTimesMs = [];
        for ($run = 0; $run < $runs; $run++) {
            $start = hrtime(true);
            foreach ($requests as $request) {
                $finder->find($request, $snapshot);
            }
            $runTimesMs[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($runTimesMs);
        $medianMs = $runTimesMs[intdiv($runs, 2)];

        // Assert median < 50ms (M-03.1, TC-03-21)
        $this->assertLessThan(
            50.0,
            $medianMs,
            sprintf(
                'Expected median find() time for 36 calls to be < 50ms, got %.3fms (runs: %s)',
                $medianMs,
                implode(', ', array_map(fn (float $t): string => sprintf('%.3fms', $t), $runTimesMs)),
            ),
        );
    }
}
