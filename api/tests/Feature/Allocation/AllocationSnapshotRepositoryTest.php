<?php

namespace Tests\Feature\Allocation;

use App\Domain\Allocation\BusyInterval;
use App\Domain\Allocation\Contracts\BusyIntervalReader;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\Repositories\AllocationSnapshotRepository;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\MasterData\Level;
use App\Models\Bay;
use App\Models\Dealership;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\World;
use Tests\TestCase;

class AllocationSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-03-19 (AC-03.13, INV-03.5):
     * Given World::saigonD7(); second run adds 10 technicians;
     * When forDay(d, '2026-11-02', now); count queries with DB::listen;
     * Then The busy-interval reader is asked for 2026-11-01T17:00Z–2026-11-02T17:00Z;
     *      technicians carry level; run 1 query count = run 2.
     */
    public function test_AC_03_13_shifts_in_utc_with_fixed_query_count(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');

        $spyReader = new class implements BusyIntervalReader
        {
            /** @var list<array{dealershipId: string, window: TimeRange, now: CarbonImmutable}> */
            public array $calls = [];

            public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array
            {
                $this->calls[] = [
                    'dealershipId' => $dealershipId,
                    'window' => $window,
                    'now' => $now,
                ];

                return [];
            }
        };

        $repository = new AllocationSnapshotRepository($spyReader);

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries) {
            $executedQueries[] = $query->sql;
        });

        // Run 1: load snapshot for Saigon D7
        $queryCountBeforeRun1 = count($executedQueries);
        $snapshot1 = $repository->forDay($dealership->id, '2026-11-02', $now);
        $run1Count = count($executedQueries) - $queryCountBeforeRun1;

        // Verify query count is within spec bound (<= 6 queries, 3 DB queries total)
        $this->assertLessThanOrEqual(6, $run1Count, 'Run 1 queries must be <= 6 per spec §B2.');
        $this->assertGreaterThan(0, $run1Count);

        // Verify busy-interval reader was called with the local day's UTC window:
        // Asia/Ho_Chi_Minh is UTC+7 -> 2026-11-02 00:00 is 2026-11-01 17:00 UTC.
        $this->assertCount(1, $spyReader->calls);
        $call1 = $spyReader->calls[0];
        $this->assertSame($dealership->id, $call1['dealershipId']);
        $this->assertSame('2026-11-01T17:00:00+00:00', $call1['window']->start->toIso8601String());
        $this->assertSame('2026-11-02T17:00:00+00:00', $call1['window']->end->toIso8601String());
        $this->assertSame(1440, $call1['window']->minutes());

        // Verify technicians in snapshot carry level
        $this->assertNotEmpty($snapshot1->technicians);
        foreach ($snapshot1->technicians as $techState) {
            $this->assertInstanceOf(Level::class, $techState->level);
            $this->assertGreaterThanOrEqual(1, $techState->level->value);
            $this->assertLessThanOrEqual(7, $techState->level->value);
        }

        $initialTechCount = count($snapshot1->technicians);

        // Second run adds 10 technicians
        for ($i = 1; $i <= 10; $i++) {
            Technician::create([
                'dealership_id' => $dealership->id,
                'name' => "Additional Tech {$i}",
                'level' => ($i % 7) + 1,
                'active' => true,
            ]);
        }

        // Run 2: load snapshot again
        $queryCountBeforeRun2 = count($executedQueries);
        $snapshot2 = $repository->forDay($dealership->id, '2026-11-02', $now);
        $run2Count = count($executedQueries) - $queryCountBeforeRun2;

        // Proves fixed query count: run 1 query count == run 2 query count (no N+1)
        $this->assertSame($run1Count, $run2Count, 'Query count must not change when technicians are added (no N+1).');
        $this->assertCount($initialTechCount + 10, $snapshot2->technicians);

        foreach ($snapshot2->technicians as $techState) {
            $this->assertInstanceOf(Level::class, $techState->level);
        }
    }

    /**
     * TC-03-29 (INV-03.5):
     * Given World with the dealership's timezone set to Europe/Berlin;
     * When AllocationSnapshotRepository::forDay(d, '2026-10-25', now) and forDay(d, '2026-10-24', now);
     * Then The reader is asked for 2026-10-24T22:00Z–2026-10-25T23:00Z on the 25th
     *      and 2026-10-23T22:00Z–2026-10-24T22:00Z on the 24th.
     */
    public function test_INV_03_5_dst_day_in_the_repository(): void
    {
        $dealership = Dealership::create([
            'code' => 'BER-01',
            'name' => 'Berlin Auto',
            'branch_name' => 'Mitte',
            'timezone' => 'Europe/Berlin',
            'phone' => '+49 30 1234567',
        ]);

        $spyReader = new class implements BusyIntervalReader
        {
            /** @var list<array{dealershipId: string, window: TimeRange, now: CarbonImmutable}> */
            public array $calls = [];

            public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array
            {
                $this->calls[] = [
                    'dealershipId' => $dealershipId,
                    'window' => $window,
                    'now' => $now,
                ];

                return [];
            }
        };

        $repository = new AllocationSnapshotRepository($spyReader);
        $now = CarbonImmutable::parse('2026-10-20 12:00:00', 'Europe/Berlin');

        // On the 25th: Fall-back DST transition day in Europe/Berlin (25-hour day)
        $snapshot25 = $repository->forDay($dealership->id, '2026-10-25', $now);
        $this->assertSame('Europe/Berlin', $snapshot25->timezone);
        $this->assertSame('2026-10-25', $snapshot25->localDate);

        // On the 24th: Regular standard day before transition (24-hour day)
        $snapshot24 = $repository->forDay($dealership->id, '2026-10-24', $now);
        $this->assertSame('2026-10-24', $snapshot24->localDate);

        $this->assertCount(2, $spyReader->calls);

        // Check 25th call: 2026-10-24T22:00Z to 2026-10-25T23:00Z (25 hours)
        $call25 = $spyReader->calls[0];
        $this->assertSame('2026-10-24T22:00:00+00:00', $call25['window']->start->toIso8601String());
        $this->assertSame('2026-10-25T23:00:00+00:00', $call25['window']->end->toIso8601String());
        $this->assertSame(1500, $call25['window']->minutes(), 'Europe/Berlin 2026-10-25 DST day must be 1500 minutes (25h).');
        $this->assertSame(25, (int) $call25['window']->start->diffInHours($call25['window']->end));

        // Check 24th call: 2026-10-23T22:00Z to 2026-10-24T22:00Z (24 hours)
        $call24 = $spyReader->calls[1];
        $this->assertSame('2026-10-23T22:00:00+00:00', $call24['window']->start->toIso8601String());
        $this->assertSame('2026-10-24T22:00:00+00:00', $call24['window']->end->toIso8601String());
        $this->assertSame(1440, $call24['window']->minutes(), 'Standard day must be 1440 minutes (24h).');
        $this->assertSame(24, (int) $call24['window']->start->diffInHours($call24['window']->end));
    }

    public function test_busy_intervals_are_mapped_onto_technicians_and_bays(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $tech = $world->technician('Trần Minh An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $techInterval = new BusyInterval(
            range: TimeRange::of('2026-11-02T02:00:00Z', '2026-11-02T03:00:00Z'),
            appointmentId: 'apt-001',
            resourceType: 'TECHNICIAN',
            resourceId: $tech->id,
        );
        $bayInterval = new BusyInterval(
            range: TimeRange::of('2026-11-02T03:00:00Z', '2026-11-02T04:00:00Z'),
            appointmentId: 'apt-002',
            resourceType: 'BAY',
            resourceId: $bay->id,
        );

        $fakeReader = new class([$techInterval, $bayInterval]) implements BusyIntervalReader
        {
            /**
             * @param  list<BusyInterval>  $intervals
             */
            public function __construct(private array $intervals) {}

            public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array
            {
                return $this->intervals;
            }
        };

        $repository = new AllocationSnapshotRepository($fakeReader);
        $snapshot = $repository->forDay($dealership->id, '2026-11-02', $now);

        // Find technician state for An
        $anState = null;
        foreach ($snapshot->technicians as $t) {
            if ($t->id === $tech->id) {
                $anState = $t;
                break;
            }
        }
        $this->assertNotNull($anState);
        $this->assertCount(1, $anState->busy);
        $this->assertSame('apt-001', $anState->busy[0]->appointmentId);

        // Find bay state for K1
        $k1State = null;
        foreach ($snapshot->bays as $b) {
            if ($b->id === $bay->id) {
                $k1State = $b;
                break;
            }
        }
        $this->assertNotNull($k1State);
        $this->assertCount(1, $k1State->busy);
        $this->assertSame('apt-002', $k1State->busy[0]->appointmentId);
    }

    public function test_inactive_technicians_and_bays_are_preserved_in_snapshot(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;

        // In World::saigonD7(), Huỳnh Tấn Phát is inactive
        $phat = $world->technician('Huỳnh Tấn Phát');
        $this->assertFalse($phat->active);

        // Deactivate bay K1
        $bayK1 = $world->bay('K1');
        $bayK1->update(['active' => false]);

        $repository = app(AllocationSnapshotRepositoryInterface::class);
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');

        $snapshot = $repository->forDay($dealership->id, '2026-11-02', $now);

        $phatState = null;
        foreach ($snapshot->technicians as $t) {
            if ($t->id === $phat->id) {
                $phatState = $t;
                break;
            }
        }
        $this->assertNotNull($phatState);
        $this->assertFalse($phatState->active, 'Huỳnh Tấn Phát must be inactive on TechnicianState.');

        $bayK1State = null;
        foreach ($snapshot->bays as $b) {
            if ($b->id === $bayK1->id) {
                $bayK1State = $b;
                break;
            }
        }
        $this->assertNotNull($bayK1State);
        $this->assertFalse($bayK1State->active, 'Bay K1 must be inactive on BayState.');
    }

    public function test_non_existent_dealership_throws_model_not_found(): void
    {
        $repository = app(AllocationSnapshotRepositoryInterface::class);
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');

        $this->expectException(ModelNotFoundException::class);
        $repository->forDay('00000000-0000-0000-0000-000000000000', '2026-11-02', $now);
    }

    public function test_invalid_local_date_throws_invalid_argument_exception(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $repository = app(AllocationSnapshotRepositoryInterface::class);
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');

        $this->expectException(InvalidArgumentException::class);
        $repository->forDay($dealership->id, 'invalid-date', $now);
    }
}
