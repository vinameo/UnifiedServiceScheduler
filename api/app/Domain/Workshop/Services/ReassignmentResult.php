<?php

namespace App\Domain\Workshop\Services;

use App\Models\Appointment;

readonly class ReassignmentResult
{
    /**
     * @param  array{id: string, name: string}|null  $technician
     * @param  array{id: string, name: string}|null  $bay
     */
    public function __construct(
        public Appointment $appointment,
        public string $outcome,
        public ?array $technician = null,
        public ?array $bay = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'appointmentId' => $this->appointment->id,
            'reference' => $this->appointment->reference,
            'outcome' => $this->outcome,
        ];

        if ($this->outcome === 'REASSIGNED') {
            $data['technician'] = $this->technician;
            $data['bay'] = $this->bay;
        }

        return $data;
    }
}
