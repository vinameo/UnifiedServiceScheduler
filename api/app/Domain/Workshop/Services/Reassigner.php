<?php

namespace App\Domain\Workshop\Services;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentEventType;
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

        if ($serviceType === null) {
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

        $range = TimeRange::of(
            CarbonImmutable::parse($neighbour->start_at),
            CarbonImmutable::parse($neighbour->end_at),
        );

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

        $updated = $this->appointmentWriter->moveTo(
            appointmentId: $neighbour->id,
            expectedVersion: $neighbour->version,
            newRange: $range,
            pairs: $allocationResult->pairs,
            event: AppointmentEventType::REASSIGNED,
            outbox: OutboxEventType::AppointmentReassigned,
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
}
