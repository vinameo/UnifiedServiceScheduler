<?php

namespace App\Domain\Workshop\Services;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\Services\AppointmentWriterInterface;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

class Reassigner implements ReassignerInterface
{
    public function __construct(
        private AppointmentWriterInterface $appointmentWriter,
        private CandidateFinderInterface $candidateFinder,
        private MasterDataRepositoryInterface $masterDataRepository,
    ) {}

    public function reassign(
        Appointment $neighbour,
        ResourceSnapshot $snapshotWithExtension,
        CarbonImmutable $now,
        ?User $actor = null,
    ): ReassignmentResult {
        $serviceType = $this->masterDataRepository->serviceTypeFor(
            (string) $neighbour->dealership_id,
            (string) $neighbour->service_type_id,
        );

        $range = TimeRange::of(
            CarbonImmutable::parse($neighbour->start_at),
            CarbonImmutable::parse($neighbour->end_at),
        );

        if ($serviceType === null) {
            return $this->displace($neighbour, $range, $now, $actor);
        }

        $request = new AllocationRequest(
            dealershipId: (string) $neighbour->dealership_id,
            requiredLevel: $serviceType->requiredLevel(),
            requiredBayType: $serviceType->required_bay_type,
            range: $range,
            preferTechnicianId: $neighbour->technician_id,
            preferBayId: $neighbour->bay_id,
            excludeAppointmentId: $neighbour->id,
        );

        $allocationResult = $this->candidateFinder->find($request, $snapshotWithExtension);

        if (empty($allocationResult->pairs)) {
            return $this->displace($neighbour, $range, $now, $actor);
        }

        $updated = $this->appointmentWriter->moveTo(
            appointmentId: $neighbour->id,
            expectedVersion: $neighbour->version,
            newRange: $range,
            pairs: $allocationResult->pairs,
            event: AppointmentEventType::REASSIGNED,
            // A hold is not a booking yet: its customer gets no reassignment message
            outbox: $neighbour->status === AppointmentStatus::HELD ? null : OutboxEventType::AppointmentReassigned,
            now: $now,
            actor: $actor,
        );

        $updated->load(['technician', 'bay']);

        $technicianData = null;
        if ($updated->technician !== null) {
            $technicianData = [
                'id' => (string) $updated->technician->id,
                'name' => (string) $updated->technician->name,
            ];
        }

        $bayData = null;
        if ($updated->bay !== null) {
            $bayData = [
                'id' => (string) $updated->bay->id,
                'name' => (string) $updated->bay->name,
            ];
        }

        return new ReassignmentResult(
            appointment: $updated,
            outcome: 'REASSIGNED',
            technician: $technicianData,
            bay: $bayData,
        );
    }

    /**
     * D-12.9: only a CONFIRMED booking may hold no slot. A held, checked-in or started neighbour
     * that cannot be moved makes the whole extend fail (D-12.2: nothing runs without allocations).
     */
    private function displace(
        Appointment $neighbour,
        TimeRange $range,
        CarbonImmutable $now,
        ?User $actor,
    ): ReassignmentResult {
        if ($neighbour->status !== AppointmentStatus::CONFIRMED) {
            throw new NoAvailability(
                reason: 'SLOT_TAKEN',
                requestedStart: $range->start,
                requestedEnd: $range->end,
                dealershipId: (string) $neighbour->dealership_id,
            );
        }

        $displaced = $this->appointmentWriter->displace(
            appointmentId: $neighbour->id,
            expectedVersion: $neighbour->version,
            reason: 'OVERRUN',
            now: $now,
            actor: $actor,
        );

        return new ReassignmentResult(
            appointment: $displaced,
            outcome: 'CONTACT_REQUIRED',
        );
    }
}
