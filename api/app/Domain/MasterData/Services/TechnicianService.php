<?php

namespace App\Domain\MasterData\Services;

use App\Domain\MasterData\Exceptions\TechnicianHasBookings;
use App\Domain\MasterData\Exceptions\TechnicianNotFound;
use App\Models\Dealership;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TechnicianService implements TechnicianServiceInterface
{
    public function __construct(
        private readonly UpcomingAssignmentsReader $upcomingAssignmentsReader,
    ) {}

    public function setActive(
        Dealership $dealership,
        Technician $technician,
        bool $active,
        ?CarbonImmutable $now = null,
    ): Technician {
        $now = $now ?? CarbonImmutable::now();

        return DB::transaction(function () use ($dealership, $technician, $active, $now) {
            /** @var Technician|null $locked */
            $locked = Technician::query()
                ->where('id', $technician->id)
                ->where('dealership_id', $dealership->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new TechnicianNotFound;
            }

            if (! $active) {
                if (! $locked->active) {
                    return $locked;
                }

                $count = $this->upcomingAssignmentsReader->count($locked->id, $now);
                if ($count > 0) {
                    $preview = $this->upcomingAssignmentsReader->preview($locked->id, $now, 20);
                    throw new TechnicianHasBookings(
                        count: $count,
                        appointments: $preview,
                        technicianName: $locked->name,
                    );
                }

                $locked->active = false;
                $locked->save();

                return $locked;
            }

            if ($locked->active) {
                return $locked;
            }

            $locked->active = true;
            $locked->save();

            return $locked;
        });
    }
}
