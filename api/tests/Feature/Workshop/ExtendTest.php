<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
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
}
