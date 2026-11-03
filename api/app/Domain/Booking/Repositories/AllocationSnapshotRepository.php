<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\Contracts\BusyIntervalReader;
use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

final readonly class AllocationSnapshotRepository implements AllocationSnapshotRepositoryInterface
{
    private MasterDataRepositoryInterface $masterDataRepository;

    public function __construct(
        private BusyIntervalReader $busyIntervalReader,
        ?MasterDataRepositoryInterface $masterDataRepository = null,
    ) {
        $this->masterDataRepository = $masterDataRepository ?? app(MasterDataRepositoryInterface::class);
    }

    public function forDay(string $dealershipId, string $localDate, CarbonImmutable $now): ResourceSnapshot
    {
        $dealership = Dealership::findOrFail($dealershipId);

        try {
            $localStart = CarbonImmutable::parse("{$localDate} 00:00:00", $dealership->timezone);
        } catch (Throwable $e) {
            throw new InvalidArgumentException("Invalid local date '{$localDate}'.", 0, $e);
        }

        $startUtc = $localStart->setTimezone('UTC');
        $endUtc = $localStart->addDay()->setTimezone('UTC');
        $dayWindow = TimeRange::of($startUtc, $endUtc);

        $technicians = $this->masterDataRepository->technicians($dealershipId);
        $bays = $this->masterDataRepository->bays($dealershipId);

        $busyIntervals = $this->busyIntervalReader->forDealership($dealershipId, $dayWindow, $now);

        $busyByTechnician = [];
        $busyByBay = [];

        foreach ($busyIntervals as $interval) {
            if ($interval->resourceId === null) {
                continue;
            }

            $type = strtoupper((string) $interval->resourceType);
            if ($type === 'TECHNICIAN') {
                $busyByTechnician[$interval->resourceId][] = $interval;
            } elseif ($type === 'BAY') {
                $busyByBay[$interval->resourceId][] = $interval;
            }
        }

        $technicianStates = [];
        foreach ($technicians as $tech) {
            $technicianStates[] = new TechnicianState(
                id: $tech->id,
                name: $tech->name,
                level: $tech->level(),
                active: (bool) $tech->active,
                busy: $busyByTechnician[$tech->id] ?? [],
            );
        }

        $bayStates = [];
        foreach ($bays as $bay) {
            $bayStates[] = new BayState(
                id: $bay->id,
                code: $bay->code,
                bayType: $bay->bay_type,
                active: (bool) $bay->active,
                busy: $busyByBay[$bay->id] ?? [],
            );
        }

        return new ResourceSnapshot(
            dealershipId: $dealership->id,
            timezone: $dealership->timezone,
            localDate: $localDate,
            technicians: $technicianStates,
            bays: $bayStates,
        );
    }
}
