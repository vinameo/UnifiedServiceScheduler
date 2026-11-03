<?php

namespace App\Domain\Allocation;

use InvalidArgumentException;

final class CandidateFinder implements CandidateFinderInterface
{
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

        // Generate candidate pairs up to maxPairs
        $pairs = [];
        foreach ($freeTechnicians as $tech) {
            foreach ($freeBays as $bay) {
                $pairs[] = new CandidatePair($tech, $bay);
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
