<?php

namespace App\Domain\Workshop\Services;

final readonly class WorkshopBoardData
{
    /**
     * @param  list<array<string, mixed>>  $bays
     * @param  list<array<string, mixed>>  $displaced
     * @param  list<array<string, mixed>>  $technicians
     * @param  list<array<string, mixed>>  $alerts
     */
    public function __construct(
        public string $date,
        public string $now,
        public string $opensAt,
        public string $closesAt,
        public array $bays,
        public array $displaced,
        public array $technicians,
        public array $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'now' => $this->now,
            'opensAt' => $this->opensAt,
            'closesAt' => $this->closesAt,
            'bays' => $this->bays,
            'displaced' => $this->displaced,
            'technicians' => $this->technicians,
            'alerts' => $this->alerts,
        ];
    }
}
