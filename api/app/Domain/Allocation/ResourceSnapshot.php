<?php

namespace App\Domain\Allocation;

use InvalidArgumentException;

final readonly class ResourceSnapshot
{
    /**
     * @param  list<TechnicianState>  $technicians
     * @param  list<BayState>  $bays
     */
    public function __construct(
        public string $dealershipId,
        public string $timezone,
        public string $localDate,
        public array $technicians = [],
        public array $bays = [],
    ) {}

    /**
     * Return a copy of this snapshot with an added busy interval for the given resource.
     * Pure: does not mutate this instance.
     *
     * @param  \BackedEnum|string  $resourceType  'TECHNICIAN' | 'BAY'
     */
    public function withBusy(
        \BackedEnum|string $resourceType,
        string $id,
        TimeRange $range,
        ?string $appointmentId = null,
    ): self {
        $type = $resourceType instanceof \BackedEnum ? (string) $resourceType->value : (string) $resourceType;
        $typeUpper = strtoupper($type);

        if ($typeUpper === 'TECHNICIAN') {
            $interval = new BusyInterval(
                range: $range,
                appointmentId: $appointmentId,
                resourceType: $typeUpper,
                resourceId: $id,
            );

            $newTechnicians = array_map(function (TechnicianState $tech) use ($id, $interval): TechnicianState {
                if ($tech->id !== $id) {
                    return $tech;
                }

                return new TechnicianState(
                    id: $tech->id,
                    name: $tech->name,
                    level: $tech->level,
                    active: $tech->active,
                    busy: [...$tech->busy, $interval],
                );
            }, $this->technicians);

            return new self(
                dealershipId: $this->dealershipId,
                timezone: $this->timezone,
                localDate: $this->localDate,
                technicians: $newTechnicians,
                bays: $this->bays,
            );
        }

        if ($typeUpper === 'BAY') {
            $interval = new BusyInterval(
                range: $range,
                appointmentId: $appointmentId,
                resourceType: $typeUpper,
                resourceId: $id,
            );

            $newBays = array_map(function (BayState $bay) use ($id, $interval): BayState {
                if ($bay->id !== $id) {
                    return $bay;
                }

                return new BayState(
                    id: $bay->id,
                    code: $bay->code,
                    bayType: $bay->bayType,
                    active: $bay->active,
                    busy: [...$bay->busy, $interval],
                );
            }, $this->bays);

            return new self(
                dealershipId: $this->dealershipId,
                timezone: $this->timezone,
                localDate: $this->localDate,
                technicians: $this->technicians,
                bays: $newBays,
            );
        }

        throw new InvalidArgumentException("Unsupported resource type for withBusy: {$type}");
    }
}
