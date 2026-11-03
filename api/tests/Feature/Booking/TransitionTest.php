<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\ResourceType;
use App\Domain\Booking\Services\AppointmentEventRecorderInterface;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class TransitionTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * @param  list<AppointmentStatus>  $path
     */
    #[DataProvider('freeingPathProvider')]
    public function test_AC_04_14_freeing_statuses_release_allocations(
        AppointmentStatus $initialStatus,
        bool $hasVehicle,
        array $path,
    ): void {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        // 1. Create appointment
        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261122-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 4),
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $hasVehicle ? $vehicle->id : null,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => $initialStatus->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'expires_at' => $initialStatus === AppointmentStatus::HELD ? $now->addMinutes(5) : null,
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Insert active allocations
        $allocations = [
            [
                'resource_type' => ResourceType::TECHNICIAN,
                'resource_id' => $tech->id,
                'start_at' => $now,
                'end_at' => $now->addHour(),
            ],
            [
                'resource_type' => ResourceType::BAY,
                'resource_id' => $bay->id,
                'start_at' => $now,
                'end_at' => $now->addHour(),
            ],
        ];

        if ($hasVehicle) {
            $allocations[] = [
                'resource_type' => ResourceType::VEHICLE,
                'resource_id' => $vehicle->id,
                'start_at' => $now,
                'end_at' => $now->addHour(),
            ];
        }

        /** @var ResourceAllocationRepositoryInterface $allocRepo */
        $allocRepo = app(ResourceAllocationRepositoryInterface::class);
        $allocRepo->insertFor($apptId, (string) $world->dealership->id, $allocations, $now);

        // 3. Record initial event
        /** @var AppointmentEventRecorderInterface $eventRecorder */
        $eventRecorder = app(AppointmentEventRecorderInterface::class);
        $eventRecorder->record(
            appointmentId: $apptId,
            dealershipId: (string) $world->dealership->id,
            type: $initialStatus === AppointmentStatus::HELD ? AppointmentEventType::HELD : AppointmentEventType::CREATED,
            version: 1,
            occurredAt: $now,
            fromStatus: null,
            toStatus: $initialStatus,
            actorUserId: $user->id,
            actorRole: 'CUSTOMER',
            data: [],
        );

        $currentVersion = 1;

        // 4. Transition along path
        foreach ($path as $targetStatus) {
            $response = $this->actingAs($user)
                ->postJson("/api/v1/_testing/appointments/{$apptId}/transition", [
                    'to' => $targetStatus->value,
                ], [
                    'Accept' => 'application/json',
                    'If-Match' => "\"{$currentVersion}\"",
                    'Idempotency-Key' => (string) Str::uuid(),
                ]);

            $currentVersion++;
            $response->assertStatus(200)
                ->assertHeader('ETag', "\"{$currentVersion}\"")
                ->assertJsonPath('status', $targetStatus->value)
                ->assertJsonPath('version', $currentVersion);
        }

        // 5. Assertions at final status
        // 0 active allocations
        $activeAllocationsCount = DB::table('resource_allocations')
            ->where('appointment_id', $apptId)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $activeAllocationsCount);

        // deactivated_at set on all allocations for this appointment
        $notDeactivatedCount = DB::table('resource_allocations')
            ->where('appointment_id', $apptId)
            ->whereNull('deactivated_at')
            ->count();
        $this->assertSame(0, $notDeactivatedCount);

        // 1 history row per step
        $events = DB::table('appointment_events')
            ->where('appointment_id', $apptId)
            ->orderBy('version')
            ->get();
        $this->assertCount(1 + count($path), $events);
        foreach ($events as $idx => $event) {
            $this->assertSame($idx + 1, $event->version);
        }

        // M-04.2 query returns 0 rows
        $invalidAppointments = DB::select(<<<'SQL'
            SELECT a.id
            FROM appointments a
            LEFT JOIN resource_allocations r ON r.appointment_id = a.id AND r.active
            GROUP BY a.id, a.status, a.vehicle_id
            HAVING (a.status IN ('HELD','CONFIRMED','CHECKED_IN','IN_PROGRESS') AND count(r.id) <> 2 + (a.vehicle_id IS NOT NULL)::int)
                OR (a.status NOT IN ('HELD','CONFIRMED','CHECKED_IN','IN_PROGRESS') AND count(r.id) > 0)
        SQL);
        $this->assertEmpty($invalidAppointments);
    }

    /**
     * @return array<string, array{0: AppointmentStatus, 1: bool, 2: list<AppointmentStatus>}>
     */
    public static function freeingPathProvider(): array
    {
        return [
            'CONFIRMED -> CANCELLED' => [
                AppointmentStatus::CONFIRMED,
                true,
                [AppointmentStatus::CANCELLED],
            ],
            'HELD -> EXPIRED with vehicle' => [
                AppointmentStatus::HELD,
                true,
                [AppointmentStatus::EXPIRED],
            ],
            'HELD -> EXPIRED without vehicle' => [
                AppointmentStatus::HELD,
                false,
                [AppointmentStatus::EXPIRED],
            ],
            'HELD -> CANCELLED without vehicle' => [
                AppointmentStatus::HELD,
                false,
                [AppointmentStatus::CANCELLED],
            ],
            'CONFIRMED -> NO_SHOW' => [
                AppointmentStatus::CONFIRMED,
                true,
                [AppointmentStatus::NO_SHOW],
            ],
            'CONFIRMED -> CHECKED_IN -> IN_PROGRESS -> COMPLETED' => [
                AppointmentStatus::CONFIRMED,
                true,
                [
                    AppointmentStatus::CHECKED_IN,
                    AppointmentStatus::IN_PROGRESS,
                    AppointmentStatus::COMPLETED,
                ],
            ],
        ];
    }

    public function test_stale_version_beats_invalid_transition(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        // Appointment v3 CONFIRMED
        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261122-0099',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 3,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Someone else moves it to CANCELLED (v4)
        DB::table('appointments')
            ->where('id', $apptId)
            ->update([
                'status' => AppointmentStatus::CANCELLED->value,
                'version' => 4,
                'updated_at' => $now->addMinute(),
            ]);

        // Transition to CHECKED_IN with If-Match: "3"
        // CANCELLED -> CHECKED_IN is an invalid transition, but because version is stale (v3 vs v4),
        // it must return 412 (not 422) with appointment.status = CANCELLED and ETag: "4"
        $response = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/transition", [
                'to' => AppointmentStatus::CHECKED_IN->value,
            ], [
                'Accept' => 'application/json',
                'If-Match' => '"3"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(412)
            ->assertHeader('ETag', '"4"')
            ->assertJsonPath('code', 'APPOINTMENT_MODIFIED')
            ->assertJsonPath('appointment.status', AppointmentStatus::CANCELLED->value)
            ->assertJsonPath('appointment.version', 4);
    }
}
