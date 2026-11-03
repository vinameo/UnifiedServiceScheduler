<?php

namespace App\Domain\Allocation;

final class LowestLevelFirstStrategy implements AllocationStrategy
{
    /**
     * @param  list<TechnicianState>  $free
     * @return list<TechnicianState>
     */
    public function rankTechnicians(array $free, AllocationRequest $request, ResourceSnapshot $snapshot): array
    {
        $ranked = $free;

        usort($ranked, function (TechnicianState $a, TechnicianState $b) use ($request): int {
            $cmp = $a->level->value <=> $b->level->value;
            if ($cmp !== 0) {
                return $cmp;
            }

            $minutesA = $this->calculateBookedMinutes($a, $request);
            $minutesB = $this->calculateBookedMinutes($b, $request);
            $cmp = $minutesA <=> $minutesB;
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a->id, $b->id);
        });

        if ($request->preferTechnicianId !== null) {
            $preferredIndex = null;
            foreach ($ranked as $index => $tech) {
                if ($tech->id === $request->preferTechnicianId) {
                    $preferredIndex = $index;
                    break;
                }
            }

            if ($preferredIndex !== null) {
                $preferred = array_splice($ranked, $preferredIndex, 1);
                array_unshift($ranked, $preferred[0]);
            }
        }

        return $ranked;
    }

    /**
     * @param  list<BayState>  $free
     * @return list<BayState>
     */
    public function rankBays(array $free, AllocationRequest $request): array
    {
        $ranked = $free;

        usort($ranked, function (BayState $a, BayState $b): int {
            $cmp = strcmp($a->code, $b->code);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a->id, $b->id);
        });

        if ($request->preferBayId !== null) {
            $preferredIndex = null;
            foreach ($ranked as $index => $bay) {
                if ($bay->id === $request->preferBayId) {
                    $preferredIndex = $index;
                    break;
                }
            }

            if ($preferredIndex !== null) {
                $preferred = array_splice($ranked, $preferredIndex, 1);
                array_unshift($ranked, $preferred[0]);
            }
        }

        return $ranked;
    }

    private function calculateBookedMinutes(TechnicianState $technician, AllocationRequest $request): int
    {
        $minutes = 0;
        foreach ($technician->busy as $interval) {
            if ($request->excludeAppointmentId !== null && $interval->appointmentId === $request->excludeAppointmentId) {
                continue;
            }

            $minutes += $interval->range->minutes();
        }

        return $minutes;
    }
}
