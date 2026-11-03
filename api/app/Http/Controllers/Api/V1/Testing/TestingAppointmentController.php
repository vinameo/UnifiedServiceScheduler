<?php

namespace App\Http\Controllers\Api\V1\Testing;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\Services\AppointmentWriterInterface;
use App\Domain\MasterData\Level;
use App\Http\AppointmentResponder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Testing\CreateTestingAppointmentRequest;
use App\Models\Dealership;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

class TestingAppointmentController extends Controller
{
    public function __construct(
        private AllocationSnapshotRepositoryInterface $snapshotRepository,
        private CandidateFinderInterface $candidateFinder,
        private AppointmentWriterInterface $appointmentWriter,
    ) {}

    public function store(CreateTestingAppointmentRequest $request): JsonResponse
    {
        /** @var string $dealershipId */
        $dealershipId = $request->validated('dealershipId');
        /** @var string $customerId */
        $customerId = $request->validated('customerId');
        /** @var string|null $vehicleId */
        $vehicleId = $request->validated('vehicleId');
        /** @var string $serviceTypeId */
        $serviceTypeId = $request->validated('serviceTypeId');
        /** @var string $startInput */
        $startInput = $request->validated('start');

        $dealership = Dealership::findOrFail($dealershipId);
        $serviceType = ServiceType::findOrFail($serviceTypeId);

        $now = CarbonImmutable::now();
        $startUtc = CarbonImmutable::parse($startInput)->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes($serviceType->duration_minutes);
        $range = TimeRange::of($startUtc, $endUtc);

        $tz = new DateTimeZone($dealership->timezone);
        $localDate = $startUtc->setTimezone($tz)->toDateString();

        $snapshot = $this->snapshotRepository->forDay($dealershipId, $localDate, $now);

        $allocationRequest = new AllocationRequest(
            dealershipId: $dealershipId,
            requiredLevel: Level::from((int) $serviceType->required_level),
            requiredBayType: $serviceType->required_bay_type,
            range: $range,
        );

        $result = $this->candidateFinder->find($allocationRequest, $snapshot);

        if (empty($result->pairs)) {
            throw new NoAvailability(
                reason: $result->failure !== null ? $result->failure->value : 'NO_SLOT_AVAILABLE',
                requestedStart: $startUtc,
                requestedEnd: $endUtc,
                dealershipId: $dealershipId,
            );
        }

        $user = $request->user();
        $userId = $user !== null ? $user->id : 1;

        $newAppointment = new NewAppointment(
            dealershipId: $dealershipId,
            customerId: $customerId,
            vehicleId: $vehicleId,
            serviceTypeId: $serviceTypeId,
            range: $range,
            status: AppointmentStatus::CONFIRMED,
            expiresAt: null,
            reminderOptIn: true,
            source: 'CUSTOMER',
            createdByUserId: (int) $userId,
        );

        $appointment = $this->appointmentWriter->create($newAppointment, $result->pairs, $now);

        return AppointmentResponder::created($appointment);
    }
}
