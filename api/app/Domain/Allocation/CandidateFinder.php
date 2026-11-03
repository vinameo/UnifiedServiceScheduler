<?php

namespace App\Domain\Allocation;

use InvalidArgumentException;

final class CandidateFinder implements CandidateFinderInterface
{
    public function __construct(
        private AllocationStrategy $strategy = new LowestLevelFirstStrategy,
    ) {}

    public function find(AllocationRequest $request, ResourceSnapshot $snapshot): AllocationResult
    {
        if ($request->dealershipId !== $snapshot->dealershipId) {
            throw new InvalidArgumentException(sprintf(
                "Dealership mismatch: request dealership '%s' does not match snapshot dealership '%s'.",
                $request->dealershipId,
                $snapshot->dealershipId,
            ));
        }

        $requestLocalDate = $request->range->start->setTimezone($snapshot->timezone)->toDateString();
        if ($requestLocalDate !== $snapshot->localDate) {
            throw new InvalidArgumentException(sprintf(
                "Date mismatch: request range starts on '%s' in timezone '%s', but snapshot is for '%s'.",
                $requestLocalDate,
                $snapshot->timezone,
                $snapshot->localDate,
            ));
        }

        // Filter qualified technicians (active and level >= requiredLevel)
        $qualifiedTechnicians = array_values(array_filter(
            $snapshot->technicians,
            fn (TechnicianState $tech): bool => $tech->active && $tech->level->qualifiesFor($request->requiredLevel),
        ));

        if (count($qualifiedTechnicians) === 0) {
            return new AllocationResult(pairs: [], failure: AllocationFailure::NO_QUALIFIED_TECHNICIAN);
        }

        // Filter free technicians
        $freeTechnicians = array_values(array_filter(
            $qualifiedTechnicians,
            fn (TechnicianState $tech): bool => ! $this->isTechnicianBusy($tech, $request),
        ));

        if (count($freeTechnicians) === 0) {
            return new AllocationResult(pairs: [], failure: AllocationFailure::NO_TECHNICIAN_AVAILABLE);
        }

        // Filter qualified and free bays
        $freeBays = array_values(array_filter(
            $snapshot->bays,
            fn (BayState $bay): bool => $bay->active
                && $bay->bayType === $request->requiredBayType
                && ! $this->isBayBusy($bay, $request),
        ));

        if (count($freeBays) === 0) {
            return new AllocationResult(pairs: [], failure: AllocationFailure::NO_BAY_AVAILABLE);
        }

        // Rank technicians and bays via strategy
        $rankedTechnicians = $this->strategy->rankTechnicians($freeTechnicians, $request, $snapshot);
        $rankedBays = $this->strategy->rankBays($freeBays, $request);

        // Generate candidate pairs per D-03.3:
        // Sum of 0-based technician rank i and bay rank j (i + j) ascending,
        // ties broken by technician rank i ascending. Take up to maxPairs.
        $pairs = [];
        $numTech = count($rankedTechnicians);
        $numBays = count($rankedBays);
        $maxSum = ($numTech - 1) + ($numBays - 1);

        for ($s = 0; $s <= $maxSum; $s++) {
            $minI = max(0, $s - ($numBays - 1));
            $maxI = min($numTech - 1, $s);

            for ($i = $minI; $i <= $maxI; $i++) {
                $j = $s - $i;
                $pairs[] = new CandidatePair($rankedTechnicians[$i], $rankedBays[$j]);

                if (count($pairs) >= $request->maxPairs) {
                    break 2;
                }
            }
        }

        return new AllocationResult(pairs: $pairs);
    }

    private function isTechnicianBusy(TechnicianState $technician, AllocationRequest $request): bool
    {
        foreach ($technician->busy as $interval) {
            if ($request->excludeAppointmentId !== null && $interval->appointmentId === $request->excludeAppointmentId) {
                continue;
            }

            if ($interval->range->overlaps($request->range)) {
                return true;
            }
        }

        return false;
    }

    private function isBayBusy(BayState $bay, AllocationRequest $request): bool
    {
        foreach ($bay->busy as $interval) {
            if ($request->excludeAppointmentId !== null && $interval->appointmentId === $request->excludeAppointmentId) {
                continue;
            }

            if ($interval->range->overlaps($request->range)) {
                return true;
            }
        }

        return false;
    }
}
