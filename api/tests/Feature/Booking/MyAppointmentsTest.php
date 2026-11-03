<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class MyAppointmentsTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

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
            'start_at' => CarbonImmutable::parse($startAt)->utc(),
            'end_at' => CarbonImmutable::parse($endAt)->utc(),
            'expires_at' => $expiresAt !== null ? CarbonImmutable::parse($expiresAt)->utc() : ($status === AppointmentStatus::HELD ? CarbonImmutable::parse($endAt)->utc() : null),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    public function test_AC_05_4_and_05_8_upcoming_and_past(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();

        ['user' => $userA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $userB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();

        // Customer A:
        // 1. Future CONFIRMED (earlier)
        $future1 = $this->createAppointment(
            world: $world,
            startAt: '2026-11-02T09:00:00+07:00',
            endAt: '2026-11-02T10:00:00+07:00',
            status: AppointmentStatus::CONFIRMED,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
        );

        // 2. Future CONFIRMED (later)
        $future2 = $this->createAppointment(
            world: $world,
            startAt: '2026-11-03T09:00:00+07:00',
            endAt: '2026-11-03T10:00:00+07:00',
            status: AppointmentStatus::CONFIRMED,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
        );

        // 3. Yesterday CONFIRMED (end_at in past)
        $yesterday = $this->createAppointment(
            world: $world,
            startAt: '2026-11-01T09:00:00+07:00',
            endAt: '2026-11-01T10:00:00+07:00',
            status: AppointmentStatus::CONFIRMED,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
        );

        // 4. Next week CANCELLED
        $cancelledNextWeek = $this->createAppointment(
            world: $world,
            startAt: '2026-11-09T09:00:00+07:00',
            endAt: '2026-11-09T10:00:00+07:00',
            status: AppointmentStatus::CANCELLED,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
        );

        // 5. HELD (future hold)
        $held = $this->createAppointment(
            world: $world,
            startAt: '2026-11-04T09:00:00+07:00',
            endAt: '2026-11-04T10:00:00+07:00',
            status: AppointmentStatus::HELD,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
            expiresAt: '2026-11-02T08:15:00+07:00',
        );

        // 6. EXPIRED
        $expired = $this->createAppointment(
            world: $world,
            startAt: '2026-11-01T14:00:00+07:00',
            endAt: '2026-11-01T15:00:00+07:00',
            status: AppointmentStatus::EXPIRED,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
            expiresAt: '2026-11-01T14:15:00+07:00',
        );

        // Customer B: 3 bookings
        $bFuture = $this->createAppointment(
            world: $world,
            startAt: '2026-11-02T11:00:00+07:00',
            endAt: '2026-11-02T12:00:00+07:00',
            status: AppointmentStatus::CONFIRMED,
            customerId: $customerB->id,
            vehicleId: $vehicleB->id,
            userId: $userB->id,
        );
        $bPast = $this->createAppointment(
            world: $world,
            startAt: '2026-11-01T11:00:00+07:00',
            endAt: '2026-11-01T12:00:00+07:00',
            status: AppointmentStatus::CONFIRMED,
            customerId: $customerB->id,
            vehicleId: $vehicleB->id,
            userId: $userB->id,
        );
        $bCancelled = $this->createAppointment(
            world: $world,
            startAt: '2026-11-10T11:00:00+07:00',
            endAt: '2026-11-10T12:00:00+07:00',
            status: AppointmentStatus::CANCELLED,
            customerId: $customerB->id,
            vehicleId: $vehicleB->id,
            userId: $userB->id,
        );

        // Customer A queries upcoming
        $upcomingResponse = $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=upcoming');

        $upcomingResponse->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.nextCursor', null)
            ->assertJsonPath('data.0.id', $future1->id)
            ->assertJsonPath('data.1.id', $future2->id);

        // Customer A queries past
        $pastResponse = $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=past');

        $pastResponse->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('meta.nextCursor', null)
            ->assertJsonPath('data.0.id', $cancelledNextWeek->id)
            ->assertJsonPath('data.1.id', $yesterday->id);
    }

    public function test_additional_statuses_in_upcoming_and_past(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        // CHECKED_IN future -> upcoming
        $checkedInFuture = $this->createAppointment(
            world: $world,
            startAt: '2026-11-02T08:30:00+07:00',
            endAt: '2026-11-02T09:30:00+07:00',
            status: AppointmentStatus::CHECKED_IN,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            userId: $user->id,
        );

        // IN_PROGRESS future -> upcoming
        $inProgressFuture = $this->createAppointment(
            world: $world,
            startAt: '2026-11-02T07:30:00+07:00',
            endAt: '2026-11-02T08:30:00+07:00',
            status: AppointmentStatus::IN_PROGRESS,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            userId: $user->id,
        );

        // COMPLETED -> past
        $completed = $this->createAppointment(
            world: $world,
            startAt: '2026-11-01T10:00:00+07:00',
            endAt: '2026-11-01T11:00:00+07:00',
            status: AppointmentStatus::COMPLETED,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            userId: $user->id,
        );

        // NO_SHOW -> past
        $noShow = $this->createAppointment(
            world: $world,
            startAt: '2026-11-01T12:00:00+07:00',
            endAt: '2026-11-01T13:00:00+07:00',
            status: AppointmentStatus::NO_SHOW,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            userId: $user->id,
        );

        // IN_PROGRESS ended in past (end_at <= now) -> past
        $inProgressPast = $this->createAppointment(
            world: $world,
            startAt: '2026-11-02T06:00:00+07:00',
            endAt: '2026-11-02T07:00:00+07:00',
            status: AppointmentStatus::IN_PROGRESS,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            userId: $user->id,
        );

        // Upcoming check: checkedInFuture and inProgressFuture
        $resUpcoming = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=upcoming');
        $resUpcoming->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // Past check: completed, noShow, inProgressPast
        $resPast = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=past');
        $resPast->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_cursor_paging_upcoming_and_past(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        // Create 5 future appointments (Nov 03 to Nov 07)
        $upcomingIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $dayNum = sprintf('%02d', $i + 2);
            $apt = $this->createAppointment(
                world: $world,
                startAt: "2026-11-{$dayNum}T09:00:00+07:00",
                endAt: "2026-11-{$dayNum}T10:00:00+07:00",
                status: AppointmentStatus::CONFIRMED,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                userId: $user->id,
            );
            $upcomingIds[] = $apt->id;
        }

        // Page 1 upcoming: limit=2
        $p1 = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=upcoming&limit=2');
        $p1->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.id', $upcomingIds[0])
            ->assertJsonPath('data.1.id', $upcomingIds[1]);
        $cursor1 = $p1->json('meta.nextCursor');
        $this->assertNotNull($cursor1);

        // Page 2 upcoming: limit=2 with cursor1
        $p2 = $this->actingAs($user)->getJson("/api/v1/me/appointments?scope=upcoming&limit=2&cursor={$cursor1}");
        $p2->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.id', $upcomingIds[2])
            ->assertJsonPath('data.1.id', $upcomingIds[3]);
        $cursor2 = $p2->json('meta.nextCursor');
        $this->assertNotNull($cursor2);

        // Page 3 upcoming: limit=2 with cursor2
        $p3 = $this->actingAs($user)->getJson("/api/v1/me/appointments?scope=upcoming&limit=2&cursor={$cursor2}");
        $p3->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.id', $upcomingIds[4])
            ->assertJsonPath('meta.nextCursor', null);

        // Now test past cursor paging (DESC order)
        // Create 5 past appointments
        $pastIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $apt = $this->createAppointment(
                world: $world,
                startAt: "2026-10-2{$i}T09:00:00+07:00",
                endAt: "2026-10-2{$i}T10:00:00+07:00",
                status: AppointmentStatus::COMPLETED,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                userId: $user->id,
            );
            $pastIds[] = $apt->id;
        }

        // Because past is start_at DESC, the order will be index 4, 3, 2, 1, 0 (Oct 25 down to Oct 21)
        $pastExpectedOrder = array_reverse($pastIds);

        $pastP1 = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=past&limit=2');
        $pastP1->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.id', $pastExpectedOrder[0])
            ->assertJsonPath('data.1.id', $pastExpectedOrder[1]);
        $pastCursor1 = $pastP1->json('meta.nextCursor');
        $this->assertNotNull($pastCursor1);

        $pastP2 = $this->actingAs($user)->getJson("/api/v1/me/appointments?scope=past&limit=2&cursor={$pastCursor1}");
        $pastP2->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.id', $pastExpectedOrder[2])
            ->assertJsonPath('data.1.id', $pastExpectedOrder[3]);
        $pastCursor2 = $pastP2->json('meta.nextCursor');
        $this->assertNotNull($pastCursor2);

        $pastP3 = $this->actingAs($user)->getJson("/api/v1/me/appointments?scope=past&limit=2&cursor={$pastCursor2}");
        $pastP3->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('data.0.id', $pastExpectedOrder[4])
            ->assertJsonPath('meta.nextCursor', null);
    }

    public function test_adversarial_inputs(): void
    {
        $world = World::saigonD7();
        ['user' => $userA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $userB, 'customer' => $customerB] = $world->customerWithVehicle();

        $this->createAppointment(
            world: $world,
            startAt: '2026-11-05T09:00:00+07:00',
            endAt: '2026-11-05T10:00:00+07:00',
            status: AppointmentStatus::CONFIRMED,
            customerId: $customerA->id,
            vehicleId: $vehicleA->id,
            userId: $userA->id,
        );

        // Missing scope -> 400
        $this->actingAs($userA)->getJson('/api/v1/me/appointments')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // scope=all -> 400 (API §B4)
        $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=all')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // limit=0 -> 400
        $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=upcoming&limit=0')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // limit=201 -> 400
        $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=upcoming&limit=201')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // limit=abc -> 400
        $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=upcoming&limit=abc')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // cursor=xyz (bad base64 or invalid payload) -> 400
        $this->actingAs($userA)->getJson('/api/v1/me/appointments?scope=upcoming&cursor=xyz')
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // customerId=<B> is ignored and returns A's booking (API §B4)
        $res = $this->actingAs($userA)->getJson("/api/v1/me/appointments?scope=upcoming&customerId={$customerB->id}");
        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer.id', $customerA->id);
    }

    public function test_authorization_checks(): void
    {
        $world = World::saigonD7();

        // 1. Unauthenticated -> 401
        $this->getJson('/api/v1/me/appointments?scope=upcoming')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        // 2. Staff user (advisor) -> 403 FORBIDDEN
        $advisor = $world->advisor();
        $this->actingAs($advisor)->getJson('/api/v1/me/appointments?scope=upcoming')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        // 3. Admin -> 403 FORBIDDEN
        $admin = $world->admin();
        $this->actingAs($admin)->getJson('/api/v1/me/appointments?scope=upcoming')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_empty_bookings_and_user_without_customer(): void
    {
        $world = World::saigonD7();
        ['user' => $user] = $world->customerWithVehicle();

        // Customer with 0 appointments
        $res = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=upcoming');
        $res->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.nextCursor', null);

        // User without customer row
        $userWithoutCustomer = User::factory()->customer()->create();
        Customer::where('user_id', $userWithoutCustomer->id)->delete();

        $resNoCustomer = $this->actingAs($userWithoutCustomer)->getJson('/api/v1/me/appointments?scope=upcoming');
        $resNoCustomer->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.nextCursor', null);
    }

    public function test_query_count_is_constant(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        // 2 appointments
        for ($i = 0; $i < 2; $i++) {
            $this->createAppointment(
                world: $world,
                startAt: CarbonImmutable::parse('2026-11-03 08:00:00+07:00')->addMinutes($i * 30)->toIso8601String(),
                endAt: CarbonImmutable::parse('2026-11-03 09:00:00+07:00')->addMinutes($i * 30)->toIso8601String(),
                status: AppointmentStatus::CONFIRMED,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                userId: $user->id,
            );
        }

        $user->load('customer');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $res2 = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=upcoming&limit=50');
        $res2->assertStatus(200)->assertJsonCount(2, 'data');

        $queryCount2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 8 more appointments (total 10)
        for ($i = 2; $i < 10; $i++) {
            $this->createAppointment(
                world: $world,
                startAt: CarbonImmutable::parse('2026-11-03 08:00:00+07:00')->addMinutes($i * 30)->toIso8601String(),
                endAt: CarbonImmutable::parse('2026-11-03 09:00:00+07:00')->addMinutes($i * 30)->toIso8601String(),
                status: AppointmentStatus::CONFIRMED,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                userId: $user->id,
            );
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $res10 = $this->actingAs($user)->getJson('/api/v1/me/appointments?scope=upcoming&limit=50');
        $res10->assertStatus(200)->assertJsonCount(10, 'data');

        $queryCount10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queryCount2,
            $queryCount10,
            "Expected query count to be constant: 2 appointments took {$queryCount2} queries, 10 took {$queryCount10} queries."
        );
    }
}
