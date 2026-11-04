<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Workshop\Services\WorkshopService;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class ExtendTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        WorkshopService::$beforeAttemptHook = null;
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-12-08 (AC-12.7; Integration): given X IN_PROGRESS, nothing after it;
     * when Extend 30; then 200; end +30; X's 3 allocations have the new range;
     * 1 EXTENDED history row with minutes 30.
     */
    public function test_AC_12_7_extend_without_neighbours(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');
        $customerData = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $startAt = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        $originalEndAt = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        $expectedNewEndAt = CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh');

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $startAt,
            end: $originalEndAt,
            serviceType: $service,
            customer: $customerData['customer'],
            vehicle: $customerData['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );

        $initialVersion = $appointment->version;

        $response = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $initialVersion),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(200);
        $newVersion = $initialVersion + 1;
        $response->assertHeader('ETag', sprintf('"%d"', $newVersion));

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertSame([], $data['affected']);
        $this->assertSame($newVersion, $data['appointment']['version']);
        $this->assertSame(
            $expectedNewEndAt->toIso8601String(),
            CarbonImmutable::parse($data['appointment']['end'])->toIso8601String()
        );

        // Verify database state: appointment end_at updated
        $appointment->refresh();
        $this->assertSame($newVersion, $appointment->version);
        $this->assertSame(
            $expectedNewEndAt->utc()->toIso8601String(),
            CarbonImmutable::parse($appointment->end_at)->utc()->toIso8601String()
        );

        // Verify active allocations: exactly 3 (tech, bay, vehicle) spanning [startAt, expectedNewEndAt)
        /** @var list<object{resource_type: string, resource_id: string, start_at: string, end_at: string, active: bool}> $allocations */
        $allocations = DB::select(<<<'SQL'
            SELECT
                resource_type,
                resource_id,
                lower(period) as start_at,
                upper(period) as end_at,
                active
            FROM resource_allocations
            WHERE appointment_id = :appointmentId
              AND active = true
            ORDER BY resource_type ASC
        SQL, ['appointmentId' => $appointment->id]);

        $this->assertCount(3, $allocations);

        foreach ($allocations as $alloc) {
            $this->assertTrue($alloc->active);
            $this->assertSame(
                $startAt->utc()->toIso8601String(),
                CarbonImmutable::parse($alloc->start_at)->toIso8601String()
            );
            $this->assertSame(
                $expectedNewEndAt->utc()->toIso8601String(),
                CarbonImmutable::parse($alloc->end_at)->toIso8601String()
            );
        }

        // Verify EXTENDED history event row
        $extendedEvents = AppointmentEvent::where('appointment_id', $appointment->id)
            ->where('type', AppointmentEventType::EXTENDED->value)
            ->get();

        $this->assertCount(1, $extendedEvents);
        /** @var AppointmentEvent $event */
        $event = $extendedEvents->first();
        $this->assertSame($newVersion, $event->version);
        $this->assertSame(30, $event->data['minutes'] ?? null);

        $this->assertNoOverlappingAllocations();
    }

    /**
     * TC-12-11 (Stale job version; Integration): given X v3; someone completes X (v4);
     * when Extend with If-Match "3"; then 412 with status COMPLETED; neighbours untouched.
     */
    public function test_stale_job_version_changes_nothing(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');
        $customerData = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $startAt = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        $endAt = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $startAt,
            end: $endAt,
            serviceType: $service,
            customer: $customerData['customer'],
            vehicle: $customerData['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        // Version 1 -> 2: Check-in
        $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/check-in",
            [],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        )->assertStatus(200);

        // Version 2 -> 3: Start work
        $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/start",
            [],
            [
                'If-Match' => '"2"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        )->assertStatus(200);

        $appointment->refresh();
        $this->assertSame(3, $appointment->version);
        $this->assertSame(AppointmentStatus::IN_PROGRESS, $appointment->status);

        // Someone completes appointment X, advancing version to 4 and status to COMPLETED
        $completeResponse = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/complete",
            [],
            [
                'If-Match' => '"3"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );
        $completeResponse->assertStatus(200);

        $appointment->refresh();
        $this->assertSame(4, $appointment->version);
        $this->assertSame(AppointmentStatus::COMPLETED, $appointment->status);

        // When foreman attempts Extend with stale If-Match "3"
        $extendResponse = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => '"3"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        // Then 412 APPOINTMENT_MODIFIED with status COMPLETED and ETag "4"
        $extendResponse->assertStatus(412)
            ->assertHeader('ETag', '"4"')
            ->assertJsonPath('code', 'APPOINTMENT_MODIFIED')
            ->assertJsonPath('appointment.status', 'COMPLETED')
            ->assertJsonPath('appointment.version', 4);

        // Verify appointment was NOT extended
        $appointment->refresh();
        $this->assertSame(4, $appointment->version);
        $this->assertSame(AppointmentStatus::COMPLETED, $appointment->status);
        $this->assertSame(
            $endAt->utc()->toIso8601String(),
            CarbonImmutable::parse($appointment->end_at)->utc()->toIso8601String()
        );

        // No EXTENDED event created
        $this->assertSame(
            0,
            AppointmentEvent::where('appointment_id', $appointment->id)
                ->where('type', AppointmentEventType::EXTENDED->value)
                ->count()
        );

        $this->assertNoOverlappingAllocations();
    }

    /**
     * Adversarial input tests for extend endpoint.
     */
    public function test_extend_validation_and_status_rules(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');
        $customerData = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // 1. Invalid minutes: string "30" -> 400
        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->subMinutes(30),
            end: $now->addMinutes(30),
            serviceType: $service,
            customer: $customerData['customer'],
            vehicle: $customerData['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );

        $resString = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/extend",
            ['minutes' => '30'],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );
        $resString->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        // Invalid minutes: 20 -> 400
        $res20 = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/extend",
            ['minutes' => 20],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );
        $res20->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');

        // 2. Extend CONFIRMED appointment -> 422 INVALID_STATUS_TRANSITION
        $confirmedAppointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->addMinutes(60),
            end: $now->addMinutes(120),
            serviceType: $service,
            customer: $customerData['customer'],
            vehicle: $customerData['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        $resConfirmed = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$confirmedAppointment->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $confirmedAppointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );
        $resConfirmed->assertStatus(422)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    /**
     * TC-12-04 (AC-12.4; Integration):
     * given X IN_PROGRESS K1 09:00–10:00 (now 10:05); Y CONFIRMED K1 10:00–11:00 (Dũng); K2 free 10:00–11:30;
     * when Extend X by 30;
     * then 200; X ends 10:30 on K1; Y REASSIGNED to K2 same time; affected lists Y.
     */
    public function test_AC_12_4_extend_moves_the_next_booking(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK1 = $world->bay('K1');
        $bayK2 = $world->bay('K2');
        $service = $world->serviceType('OIL_CHANGE');

        $customerDataX = $world->customerWithVehicle();
        $customerDataY = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 10:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $startX = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        $endX = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        $expectedNewEndX = CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh');

        $appointmentX = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techAn,
            bay: $bayK1,
            start: $startX,
            end: $endX,
            serviceType: $service,
            customer: $customerDataX['customer'],
            vehicle: $customerDataX['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );

        $startY = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        $endY = CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh');

        $appointmentY = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techDung,
            bay: $bayK1,
            start: $startY,
            end: $endY,
            serviceType: $service,
            customer: $customerDataY['customer'],
            vehicle: $customerDataY['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        $versionX = $appointmentX->version;
        $versionY = $appointmentY->version;

        $response = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointmentX->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $versionX),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertSame(
            $expectedNewEndX->toIso8601String(),
            CarbonImmutable::parse($data['appointment']['end'])->toIso8601String()
        );

        // Affected should list Y as REASSIGNED to K2 with technician Dũng
        $this->assertCount(1, $data['affected']);
        $affectedY = $data['affected'][0];
        $this->assertSame($appointmentY->id, $affectedY['appointmentId']);
        $this->assertSame($appointmentY->reference, $affectedY['reference']);
        $this->assertSame('REASSIGNED', $affectedY['outcome']);
        $this->assertSame($techDung->id, $affectedY['technician']['id']);
        $this->assertSame($bayK2->id, $affectedY['bay']['id']);

        // Check DB state for X: ends at 10:30 on K1
        $appointmentX->refresh();
        $this->assertSame($versionX + 1, $appointmentX->version);
        $this->assertSame($bayK1->id, $appointmentX->bay_id);
        $this->assertSame(
            $expectedNewEndX->utc()->toIso8601String(),
            CarbonImmutable::parse($appointmentX->end_at)->utc()->toIso8601String()
        );

        // Check DB state for Y: same time, moved to bay K2
        $appointmentY->refresh();
        $this->assertSame($versionY + 1, $appointmentY->version);
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointmentY->status);
        $this->assertSame($techDung->id, $appointmentY->technician_id);
        $this->assertSame($bayK2->id, $appointmentY->bay_id);
        $this->assertSame(
            $startY->utc()->toIso8601String(),
            CarbonImmutable::parse($appointmentY->start_at)->utc()->toIso8601String()
        );
        $this->assertSame(
            $endY->utc()->toIso8601String(),
            CarbonImmutable::parse($appointmentY->end_at)->utc()->toIso8601String()
        );

        // Active allocations: Y has 3 active allocations on (Dũng, K2, vehicle)
        $allocationsY = DB::table('resource_allocations')
            ->where('appointment_id', $appointmentY->id)
            ->where('active', true)
            ->get();
        $this->assertCount(3, $allocationsY);
        $bayAllocY = $allocationsY->firstWhere('resource_type', 'BAY');
        $this->assertNotNull($bayAllocY);
        $this->assertSame($bayK2->id, $bayAllocY->resource_id);

        // REASSIGNED event recorded for Y
        $reassignedEvents = AppointmentEvent::where('appointment_id', $appointmentY->id)
            ->where('type', AppointmentEventType::REASSIGNED->value)
            ->get();
        $this->assertCount(1, $reassignedEvents);

        $this->assertNoOverlappingAllocations();
        $this->assertM042Passes();
    }

    /**
     * TC-12-09 (AC-12.4, D-12.9; Integration):
     * given As TC-12-04 but no other QUICK bay free;
     * when Extend X by 30;
     * then 200; Y still CONFIRMED, contact_required_reason OVERRUN, 0 active allocations;
     * X holds K1 to 10:30; M-04.2 (with exception) 0 rows.
     */
    public function test_AC_12_4_unplaceable_neighbour_is_displaced_not_cancelled(): void
    {
        $world = World::saigonD7();
        // Deactivate all bays except K1, K3, K4 (so no other QUICK bay is available)
        $world->onlyBays(['K1', 'K3', 'K4']);

        $foreman = $world->foreman();

        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK1 = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $customerDataX = $world->customerWithVehicle();
        $customerDataY = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 10:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $startX = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        $endX = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        $expectedNewEndX = CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh');

        $appointmentX = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techAn,
            bay: $bayK1,
            start: $startX,
            end: $endX,
            serviceType: $service,
            customer: $customerDataX['customer'],
            vehicle: $customerDataX['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );

        $startY = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        $endY = CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh');

        $appointmentY = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techDung,
            bay: $bayK1,
            start: $startY,
            end: $endY,
            serviceType: $service,
            customer: $customerDataY['customer'],
            vehicle: $customerDataY['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        $versionX = $appointmentX->version;
        $versionY = $appointmentY->version;

        $response = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointmentX->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $versionX),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertSame(
            $expectedNewEndX->toIso8601String(),
            CarbonImmutable::parse($data['appointment']['end'])->toIso8601String()
        );

        // Affected should list Y as CONTACT_REQUIRED
        $this->assertCount(1, $data['affected']);
        $affectedY = $data['affected'][0];
        $this->assertSame($appointmentY->id, $affectedY['appointmentId']);
        $this->assertSame($appointmentY->reference, $affectedY['reference']);
        $this->assertSame('CONTACT_REQUIRED', $affectedY['outcome']);
        $this->assertArrayNotHasKey('technician', $affectedY);
        $this->assertArrayNotHasKey('bay', $affectedY);

        // Check DB state for X: holds K1 to 10:30
        $appointmentX->refresh();
        $this->assertSame($versionX + 1, $appointmentX->version);
        $this->assertSame(
            $expectedNewEndX->utc()->toIso8601String(),
            CarbonImmutable::parse($appointmentX->end_at)->utc()->toIso8601String()
        );

        // Check DB state for Y: still CONFIRMED, contact_required_reason OVERRUN, 0 active allocations
        $appointmentY->refresh();
        $this->assertSame($versionY + 1, $appointmentY->version);
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointmentY->status);
        $this->assertSame('OVERRUN', $appointmentY->contact_required_reason);

        $activeAllocationsYCount = DB::table('resource_allocations')
            ->where('appointment_id', $appointmentY->id)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $activeAllocationsYCount);

        // CONTACT_REQUIRED event recorded for Y
        $contactEvents = AppointmentEvent::where('appointment_id', $appointmentY->id)
            ->where('type', AppointmentEventType::CONTACT_REQUIRED->value)
            ->get();
        $this->assertCount(1, $contactEvents);
        /** @var AppointmentEvent $contactEvent */
        $contactEvent = $contactEvents->first();
        $this->assertSame('OVERRUN', $contactEvent->data['reason'] ?? null);

        $this->assertNoOverlappingAllocations();
        $this->assertM042Passes();
    }

    /**
     * TC-12-10 (AC-12.8, INV-12.4; Integration):
     * given As TC-12-04; a test hook changes Y (version bump) right after the neighbour read on the first attempt;
     * when Extend;
     * then Attempt 2 succeeds with fresh data (200); never a state where X is extended but Y still holds K1.
     */
    public function test_AC_12_8_neighbour_change_triggers_one_retry(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK1 = $world->bay('K1');
        $bayK2 = $world->bay('K2');
        $service = $world->serviceType('OIL_CHANGE');

        $customerDataX = $world->customerWithVehicle();
        $customerDataY = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 10:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $startX = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        $endX = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');

        $appointmentX = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techAn,
            bay: $bayK1,
            start: $startX,
            end: $endX,
            serviceType: $service,
            customer: $customerDataX['customer'],
            vehicle: $customerDataX['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );

        $startY = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        $endY = CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh');

        $appointmentY = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techDung,
            bay: $bayK1,
            start: $startY,
            end: $endY,
            serviceType: $service,
            customer: $customerDataY['customer'],
            vehicle: $customerDataY['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        $hookRunCount = 0;
        WorkshopService::$beforeAttemptHook = function (int $attempt, Appointment $job, array $neighbours) use (&$hookRunCount, $appointmentY): void {
            $hookRunCount++;
            if ($attempt === 1 && ! empty($neighbours)) {
                // Simulate concurrent change on neighbour Y before attempt 1's transaction
                DB::table('appointments')
                    ->where('id', $appointmentY->id)
                    ->update([
                        'version' => DB::raw('version + 1'),
                        'updated_at' => CarbonImmutable::now(),
                    ]);
            }
        };

        $response = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointmentX->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $appointmentX->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(200);
        $this->assertSame(2, $hookRunCount);

        // Verify X and Y were both updated cleanly
        $appointmentX->refresh();
        $appointmentY->refresh();

        $this->assertSame($bayK1->id, $appointmentX->bay_id);
        $this->assertSame($bayK2->id, $appointmentY->bay_id);

        $this->assertNoOverlappingAllocations();
        $this->assertM042Passes();
    }
}
