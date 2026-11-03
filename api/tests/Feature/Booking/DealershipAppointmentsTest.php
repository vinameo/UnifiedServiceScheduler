<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class DealershipAppointmentsTest extends TestCase
{
    use DatabaseTruncation;

    private function createAppointment(
        World $world,
        string $startAt,
        string $endAt,
        AppointmentStatus $status = AppointmentStatus::CONFIRMED,
        ?string $technicianId = null,
        ?string $bayId = null,
        ?string $customerId = null,
        ?string $vehicleId = null,
        ?int $userId = null,
        ?string $expiresAt = null,
    ): Appointment {
        if ($customerId === null || $vehicleId === null || $userId === null) {
            ['user' => $defaultUser, 'customer' => $defaultCustomer, 'vehicle' => $defaultVehicle] = $world->customerWithVehicle();
            $userId ??= $defaultUser->id;
            $customerId ??= $defaultCustomer->id;
            $vehicleId ??= $defaultVehicle->id;
        }

        $techId = $technicianId ?? $world->technician('Trần Minh An')->id;
        $bId = $bayId ?? $world->bay('K1')->id;
        $service = $world->serviceType('OIL_CHANGE');

        return Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-'.strtoupper(Str::random(12)),
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'service_type_id' => $service->id,
            'technician_id' => $techId,
            'bay_id' => $bId,
            'status' => $status,
            'start_at' => CarbonImmutable::parse($startAt),
            'end_at' => CarbonImmutable::parse($endAt),
            'expires_at' => $expiresAt !== null ? CarbonImmutable::parse($expiresAt) : ($status === AppointmentStatus::HELD ? CarbonImmutable::parse($endAt) : null),
            'version' => 1,
            'source' => 'STAFF',
            'reminder_opt_in' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    public function test_AC_05_2_date_is_local_to_the_dealership(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        // 2026-11-01T23:30:00Z is 2026-11-02 06:30:00 in Asia/Ho_Chi_Minh (+07:00)
        $appointment = $this->createAppointment(
            world: $world,
            startAt: '2026-11-01T23:30:00Z',
            endAt: '2026-11-02T00:30:00Z',
        );

        // Filter date 2026-11-02 should include the appointment
        $responseLocalDay = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date=2026-11-02");

        $responseLocalDay->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $appointment->id)
            ->assertJsonPath('meta.total', 1);

        // Filter date 2026-11-01 should NOT include the appointment
        $responsePreviousDay = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date=2026-11-01");

        $responsePreviousDay->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_AC_05_7_cursor_paging_has_no_gaps_or_duplicates(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        $tech = $world->technician('Trần Minh An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        // Create 120 bookings on 2026-11-02
        // 10 with the exact same start_at (09:00:00+07:00)
        // 110 with different start times
        $appointments = [];
        $baseDate = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');

        for ($i = 0; $i < 10; $i++) {
            $appointments[] = Appointment::create([
                'id' => (string) Str::uuid(),
                'reference' => 'APT-SAME-'.sprintf('%04d', $i + 1),
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'service_type_id' => $service->id,
                'technician_id' => $tech->id,
                'bay_id' => $bay->id,
                'status' => AppointmentStatus::CONFIRMED,
                'start_at' => $baseDate->setTime(9, 0, 0),
                'end_at' => $baseDate->setTime(10, 0, 0),
                'version' => 1,
                'source' => 'STAFF',
                'reminder_opt_in' => true,
                'created_by_user_id' => $user->id,
            ]);
        }

        for ($i = 10; $i < 120; $i++) {
            // Minute-staggered start times within the same day
            $start = $baseDate->addMinutes($i);
            $appointments[] = Appointment::create([
                'id' => (string) Str::uuid(),
                'reference' => 'APT-DIST-'.sprintf('%04d', $i + 1),
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'service_type_id' => $service->id,
                'technician_id' => $tech->id,
                'bay_id' => $bay->id,
                'status' => AppointmentStatus::CONFIRMED,
                'start_at' => $start,
                'end_at' => $start->addHour(),
                'version' => 1,
                'source' => 'STAFF',
                'reminder_opt_in' => true,
                'created_by_user_id' => $user->id,
            ]);
        }

        // Page 1: limit=50
        $page1Response = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date=2026-11-02&limit=50");

        $page1Response->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.total', 120);

        $cursor1 = $page1Response->json('meta.nextCursor');
        $this->assertNotNull($cursor1);

        // Page 2: limit=50 with cursor1
        $page2Response = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date=2026-11-02&limit=50&cursor={$cursor1}");

        $page2Response->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.total', 120);

        $cursor2 = $page2Response->json('meta.nextCursor');
        $this->assertNotNull($cursor2);

        // Page 3: limit=50 with cursor2
        $page3Response = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date=2026-11-02&limit=50&cursor={$cursor2}");

        $page3Response->assertStatus(200)
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.total', 120)
            ->assertJsonPath('meta.nextCursor', null);

        // Combine all items
        $p1Data = $page1Response->json('data');
        $p2Data = $page2Response->json('data');
        $p3Data = $page3Response->json('data');

        $allData = array_merge($p1Data, $p2Data, $p3Data);
        $this->assertCount(120, $allData);

        // Distinct IDs check: 120 distinct items
        $ids = array_map(fn ($item) => $item['id'], $allData);
        $this->assertSame(120, count(array_unique($ids)));

        // Stable order check: (start_at ASC, id ASC)
        for ($i = 0; $i < count($allData) - 1; $i++) {
            $curr = $allData[$i];
            $next = $allData[$i + 1];

            $currStart = CarbonImmutable::parse($curr['start'])->toIso8601String();
            $nextStart = CarbonImmutable::parse($next['start'])->toIso8601String();

            $cmp = strcmp($currStart, $nextStart);
            $this->assertTrue(
                $cmp < 0 || ($cmp === 0 && strcmp($curr['id'], $next['id']) < 0),
                "Order violated between index {$i} ({$curr['id']}) and index ".($i + 1)." ({$next['id']})"
            );
        }
    }

    public function test_AC_05_6_filters(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $an = $world->technician('Trần Minh An');
        $dung = $world->technician('Lý Hoàng Dũng');
        $chi = $world->technician('Võ Thị Chi');

        $k1 = $world->bay('K1');
        $k2 = $world->bay('K2');
        $k3 = $world->bay('K3');

        $day = '2026-11-02';

        // 1. An / K1 CONFIRMED
        $this->createAppointment(
            world: $world,
            startAt: "{$day}T08:00:00+07:00",
            endAt: "{$day}T09:00:00+07:00",
            status: AppointmentStatus::CONFIRMED,
            technicianId: $an->id,
            bayId: $k1->id,
        );

        // 2. Dũng / K2 CHECKED_IN
        $this->createAppointment(
            world: $world,
            startAt: "{$day}T09:00:00+07:00",
            endAt: "{$day}T10:00:00+07:00",
            status: AppointmentStatus::CHECKED_IN,
            technicianId: $dung->id,
            bayId: $k2->id,
        );

        // 3. An / K2 CANCELLED
        $this->createAppointment(
            world: $world,
            startAt: "{$day}T10:00:00+07:00",
            endAt: "{$day}T11:00:00+07:00",
            status: AppointmentStatus::CANCELLED,
            technicianId: $an->id,
            bayId: $k2->id,
        );

        // 4. EXPIRED
        $this->createAppointment(
            world: $world,
            startAt: "{$day}T11:00:00+07:00",
            endAt: "{$day}T12:00:00+07:00",
            status: AppointmentStatus::EXPIRED,
            technicianId: $an->id,
            bayId: $k1->id,
            expiresAt: "{$day}T10:30:00+07:00",
        );

        // 5. Chi / K3 COMPLETED
        $this->createAppointment(
            world: $world,
            startAt: "{$day}T13:00:00+07:00",
            endAt: "{$day}T14:00:00+07:00",
            status: AppointmentStatus::COMPLETED,
            technicianId: $chi->id,
            bayId: $k3->id,
        );

        // Filter technicianId=An -> 2 bookings (An/K1 CONFIRMED, An/K2 CANCELLED; EXPIRED excluded by default)
        $resAn = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date={$day}&technicianId={$an->id}");
        $resAn->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // Filter bayId=K2 -> 2 bookings (Dũng/K2 CHECKED_IN, An/K2 CANCELLED)
        $resK2 = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date={$day}&bayId={$k2->id}");
        $resK2->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // Filter status=CONFIRMED,CHECKED_IN -> 2 bookings (An/K1 CONFIRMED, Dũng/K2 CHECKED_IN)
        $resStatus = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date={$day}&status=CONFIRMED,CHECKED_IN");
        $resStatus->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // No filter -> 4 bookings (all except EXPIRED)
        $resNoFilter = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date={$day}");
        $resNoFilter->assertStatus(200)
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 4);
    }

    public function test_INV_05_6_foreign_filter_returns_nothing(): void
    {
        $worldD1 = World::saigonD7();
        $advisorD1 = $worldD1->advisor();

        $dealershipD2 = Dealership::factory()->create();
        $worldD2 = World::forDealership($dealershipD2);
        $d2Tech = $worldD2->technician('Trần Minh An');

        // Create appointment in D1
        $this->createAppointment(
            world: $worldD1,
            startAt: '2026-11-02T09:00:00+07:00',
            endAt: '2026-11-02T10:00:00+07:00',
        );

        // D1 advisor filters by D2 technician ID -> 200 with empty data
        $response = $this->actingAs($advisorD1)
            ->getJson("/api/v1/dealerships/{$worldD1->dealership->id}/appointments?date=2026-11-02&technicianId={$d2Tech->id}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_AC_00_7_customer_cannot_list_staff_appointments(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser] = $world->customerWithVehicle();

        $response = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date=2026-11-02");

        $response->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_query_count_is_constant(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $day = '2026-11-02';

        // 1. Create 5 appointments
        for ($i = 0; $i < 5; $i++) {
            $this->createAppointment(
                world: $world,
                startAt: CarbonImmutable::parse("{$day} 08:00:00", 'Asia/Ho_Chi_Minh')->addMinutes($i * 30)->toIso8601String(),
                endAt: CarbonImmutable::parse("{$day} 09:00:00", 'Asia/Ho_Chi_Minh')->addMinutes($i * 30)->toIso8601String(),
            );
        }

        // Count queries for 5 appointments
        DB::flushQueryLog();
        DB::enableQueryLog();

        $res5 = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date={$day}&limit=50");
        $res5->assertStatus(200)->assertJsonCount(5, 'data');

        $queryCount5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 2. Create 45 more appointments (total 50)
        for ($i = 5; $i < 50; $i++) {
            $this->createAppointment(
                world: $world,
                startAt: CarbonImmutable::parse("{$day} 08:00:00", 'Asia/Ho_Chi_Minh')->addMinutes($i * 10)->toIso8601String(),
                endAt: CarbonImmutable::parse("{$day} 09:00:00", 'Asia/Ho_Chi_Minh')->addMinutes($i * 10)->toIso8601String(),
            );
        }

        // Count queries for 50 appointments
        DB::flushQueryLog();
        DB::enableQueryLog();

        $res50 = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/appointments?date={$day}&limit=50");
        $res50->assertStatus(200)->assertJsonCount(50, 'data');

        $queryCount50 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCount5, $queryCount50, "Expected query count to be constant: 5 appointments took {$queryCount5} queries, 50 appointments took {$queryCount50} queries.");
    }
}
