<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class ViewAppointmentsAcceptanceTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * Set up standard Saigon Auto D7 world with frozen clock at 2026-11-02 08:00 +07:00,
     * advisor Minh Anh, customer Hùng (hung@example.test), customer Khánh (khanh@example.test),
     * and seeded appointments.
     *
     * @return array{
     *     world: World,
     *     dealership: Dealership,
     *     advisor: User,
     *     hungUser: User,
     *     khanhUser: User,
     *     hungAppointment1: Appointment,
     *     hungAppointment2: Appointment,
     *     dungAppointment: Appointment
     * }
     */
    private function setupAcceptanceScenario(): array
    {
        $frozenTime = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($frozenTime);

        $world = World::saigonD7();
        $dealership = $world->dealership;

        $advisor = $world->advisor([
            'name' => 'Minh Anh',
            'email' => 'advisor@saigonauto.test',
        ]);

        $an = $world->technician('Trần Minh An');
        $dung = $world->technician('Lý Hoàng Dũng');
        $bayK1 = $world->bay('K1');
        $bayK2 = $world->bay('K2');
        $oilChange = $world->serviceType('OIL_CHANGE');

        // Customer Hùng
        $hungUser = User::factory()->customer()->create([
            'name' => 'Nguyễn Văn Hùng',
            'email' => 'hung@example.test',
        ]);
        ['customer' => $hungCustomer, 'vehicle' => $hungVehicle] = $world->customerWithVehicle($hungUser, [
            'plate_key' => '51H12345',
            'licence_plate' => '51H-123.45',
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2021,
        ]);
        $hungCustomer->update(['phone' => '0903123218']);

        // Customer Khánh
        $khanhUser = User::factory()->customer()->create([
            'name' => 'Trần Quốc Khánh',
            'email' => 'khanh@example.test',
        ]);
        ['customer' => $khanhCustomer, 'vehicle' => $khanhVehicle] = $world->customerWithVehicle($khanhUser, [
            'plate_key' => '51K67890',
            'licence_plate' => '51K-678.90',
            'make' => 'Honda',
            'model' => 'City',
            'year' => 2022,
        ]);
        $khanhCustomer->update(['phone' => '0918765432']);

        // Appointment 1: Hùng, technician Trần Minh An, Bay K1, today 09:00 - 10:00 local
        $hungAppointment1 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0042',
            'dealership_id' => $dealership->id,
            'customer_id' => $hungCustomer->id,
            'vehicle_id' => $hungVehicle->id,
            'service_type_id' => $oilChange->id,
            'technician_id' => $an->id,
            'bay_id' => $bayK1->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh')->utc(),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh')->utc(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $hungUser->id,
        ]);

        // Event history for Appointment 1: CREATED and CONFIRMED
        DB::table('appointment_events')->insert([
            [
                'id' => (string) Str::uuid(),
                'appointment_id' => $hungAppointment1->id,
                'dealership_id' => $dealership->id,
                'type' => 'CREATED',
                'from_status' => null,
                'to_status' => 'CONFIRMED',
                'version' => 1,
                'actor_user_id' => $hungUser->id,
                'actor_role' => 'CUSTOMER',
                'data' => json_encode(['note' => 'Booked online', 'phone' => '0903123218', 'email' => 'hung@example.test']),
                'occurred_at' => CarbonImmutable::parse('2026-10-28 16:40:00', 'Asia/Ho_Chi_Minh')->utc(),
            ],
            [
                'id' => (string) Str::uuid(),
                'appointment_id' => $hungAppointment1->id,
                'dealership_id' => $dealership->id,
                'type' => 'CONFIRMED',
                'from_status' => 'CONFIRMED',
                'to_status' => 'CONFIRMED',
                'version' => 1,
                'actor_user_id' => $advisor->id,
                'actor_role' => 'SERVICE_ADVISOR',
                'data' => json_encode(['note' => 'Confirmed by advisor']),
                'occurred_at' => CarbonImmutable::parse('2026-10-28 16:41:00', 'Asia/Ho_Chi_Minh')->utc(),
            ],
        ]);

        // Appointment 2: Hùng, technician Trần Minh An, Bay K1, today 14:00 - 15:00 local
        $hungAppointment2 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0088',
            'dealership_id' => $dealership->id,
            'customer_id' => $hungCustomer->id,
            'vehicle_id' => $hungVehicle->id,
            'service_type_id' => $oilChange->id,
            'technician_id' => $an->id,
            'bay_id' => $bayK1->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 14:00:00', 'Asia/Ho_Chi_Minh')->utc(),
            'end_at' => CarbonImmutable::parse('2026-11-02 15:00:00', 'Asia/Ho_Chi_Minh')->utc(),
            'version' => 1,
            'source' => 'STAFF',
            'reminder_opt_in' => false,
            'created_by_user_id' => $advisor->id,
        ]);

        // Appointment 3: Khánh, technician Lý Hoàng Dũng, Bay K2, today 10:30 - 11:30 local
        $dungAppointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0055',
            'dealership_id' => $dealership->id,
            'customer_id' => $khanhCustomer->id,
            'vehicle_id' => $khanhVehicle->id,
            'service_type_id' => $oilChange->id,
            'technician_id' => $dung->id,
            'bay_id' => $bayK2->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh')->utc(),
            'end_at' => CarbonImmutable::parse('2026-11-02 11:30:00', 'Asia/Ho_Chi_Minh')->utc(),
            'version' => 1,
            'source' => 'STAFF',
            'reminder_opt_in' => true,
            'created_by_user_id' => $advisor->id,
        ]);

        return [
            'world' => $world,
            'dealership' => $dealership,
            'advisor' => $advisor,
            'hungUser' => $hungUser,
            'khanhUser' => $khanhUser,
            'hungAppointment1' => $hungAppointment1,
            'hungAppointment2' => $hungAppointment2,
            'dungAppointment' => $dungAppointment,
        ];
    }

    /**
     * TC-05-19 M1: Advisor lists appointments today.
     * Expects today's appointments in dealership timezone with meta.total.
     */
    public function test_TC_05_19_M1_advisor_lists_today_appointments_in_dealership_timezone(): void
    {
        $ctx = $this->setupAcceptanceScenario();

        $response = $this->actingAs($ctx['advisor'])
            ->getJson("/api/v1/dealerships/{$ctx['dealership']->id}/appointments?date=2026-11-02");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(3, 'data');

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');

        // Check local dealership timezone formatting (Asia/Ho_Chi_Minh = +07:00)
        $this->assertStringEndsWith('+07:00', (string) $data[0]['start']);
        $this->assertStringEndsWith('+07:00', (string) $data[0]['end']);
        $this->assertSame('Asia/Ho_Chi_Minh', $data[0]['dealership']['timezone']);
        $this->assertSame('2026-11-02T09:00:00+07:00', $data[0]['start']);
        $this->assertSame('2026-11-02T10:00:00+07:00', $data[0]['end']);
    }

    /**
     * TC-05-19 M2: Advisor filters technicianId = Trần Minh An.
     * Returns only An's appointments; Dũng's appointment is excluded.
     */
    public function test_TC_05_19_M2_advisor_filters_by_technician(): void
    {
        $ctx = $this->setupAcceptanceScenario();
        $an = $ctx['world']->technician('Trần Minh An');

        $response = $this->actingAs($ctx['advisor'])
            ->getJson("/api/v1/dealerships/{$ctx['dealership']->id}/appointments?date=2026-11-02&technicianId={$an->id}");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');

        foreach ($data as $appointment) {
            $this->assertSame($an->id, $appointment['technician']['id']);
            $this->assertSame('Trần Minh An', $appointment['technician']['name']);
        }

        // Assert Lý Hoàng Dũng's appointment is not in the filtered results
        $ids = array_column($data, 'id');
        $this->assertNotContains($ctx['dungAppointment']->id, $ids);
    }

    /**
     * TC-05-19 M3: Repeated request with the same filter yields identical results.
     * Simulates page refresh (F5) preserving filter state with stable deterministic order.
     */
    public function test_TC_05_19_M3_repeated_request_with_same_filter_yields_identical_results(): void
    {
        $ctx = $this->setupAcceptanceScenario();
        $an = $ctx['world']->technician('Trần Minh An');

        $url = "/api/v1/dealerships/{$ctx['dealership']->id}/appointments?date=2026-11-02&technicianId={$an->id}";

        $response1 = $this->actingAs($ctx['advisor'])->getJson($url);
        $response1->assertStatus(200);

        $response2 = $this->actingAs($ctx['advisor'])->getJson($url);
        $response2->assertStatus(200);

        $this->assertSame($response1->json(), $response2->json());
    }

    /**
     * TC-05-19 M4: Advisor views an appointment.
     * Receives details with ETag, phone (sanitized in history, full in appointment details),
     * and history with 'CREATED'/'CONFIRMED'.
     */
    public function test_TC_05_19_M4_advisor_views_appointment_details_etag_phone_and_history(): void
    {
        $ctx = $this->setupAcceptanceScenario();
        $apptId = $ctx['hungAppointment1']->id;

        // Step 1: GET /api/v1/appointments/{id}
        $showResponse = $this->actingAs($ctx['advisor'])->getJson("/api/v1/appointments/{$apptId}");

        $showResponse->assertStatus(200)
            ->assertHeader('ETag', '"1"')
            ->assertJsonPath('customer.fullName', 'Nguyễn Văn Hùng')
            ->assertJsonPath('customer.phone', '0903123218') // Full phone in appointment details
            ->assertJsonPath('reference', 'APT-20261102-0042')
            ->assertJsonPath('status', 'CONFIRMED');

        // Step 2: GET /api/v1/appointments/{id}/history
        $historyResponse = $this->actingAs($ctx['advisor'])->getJson("/api/v1/appointments/{$apptId}/history");

        $historyResponse->assertStatus(200)
            ->assertJsonCount(2);

        /** @var list<array<string, mixed>> $events */
        $events = $historyResponse->json();

        // Event types present
        $types = array_column($events, 'type');
        $this->assertContains('CREATED', $types);
        $this->assertContains('CONFIRMED', $types);

        // Assert phone and email personal data are completely sanitized in history payload
        $rawHistory = (string) $historyResponse->getContent();
        $this->assertStringNotContainsString('0903123218', $rawHistory);
        $this->assertStringNotContainsString('hung@example.test', $rawHistory);
        $this->assertStringNotContainsString('"phone"', $rawHistory);
        $this->assertStringNotContainsString('"email"', $rawHistory);
    }

    /**
     * TC-05-19 M5: Customer Hùng views my bookings.
     * Appointment is in Upcoming scope, customer user email matches.
     */
    public function test_TC_05_19_M5_customer_hung_views_my_bookings_upcoming(): void
    {
        $ctx = $this->setupAcceptanceScenario();

        $this->assertSame('hung@example.test', $ctx['hungUser']->email);

        $response = $this->actingAs($ctx['hungUser'])
            ->getJson('/api/v1/me/appointments?scope=upcoming');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');

        $returnedIds = array_column($data, 'id');
        $this->assertContains($ctx['hungAppointment1']->id, $returnedIds);
        $this->assertContains($ctx['hungAppointment2']->id, $returnedIds);

        // Customer Khánh's appointment must not appear
        $this->assertNotContains($ctx['dungAppointment']->id, $returnedIds);
    }

    /**
     * TC-05-19 M6: Customer Khánh attempts to read Hùng's appointment ID.
     * Receives 404 APPOINTMENT_NOT_FOUND (not 403, preventing existence disclosure).
     */
    public function test_TC_05_19_M6_customer_khanh_attempts_to_read_hungs_appointment_gets_404(): void
    {
        $ctx = $this->setupAcceptanceScenario();
        $hungApptId = $ctx['hungAppointment1']->id;

        $response = $this->actingAs($ctx['khanhUser'])
            ->getJson("/api/v1/appointments/{$hungApptId}");

        $response->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND')
            ->assertJsonPath('title', "We couldn't find this appointment.");
    }

    /**
     * TC-05-19: Comprehensive end-to-end acceptance scenario traversing M1 through M6.
     */
    public function test_TC_05_19_full_acceptance_scenario_M1_through_M6(): void
    {
        $ctx = $this->setupAcceptanceScenario();
        $dealershipId = $ctx['dealership']->id;
        $an = $ctx['world']->technician('Trần Minh An');
        $hungApptId = $ctx['hungAppointment1']->id;

        // M1: Advisor lists appointments today
        $m1Response = $this->actingAs($ctx['advisor'])
            ->getJson("/api/v1/dealerships/{$dealershipId}/appointments?date=2026-11-02");
        $m1Response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);

        // M2: Advisor filters technicianId = Trần Minh An
        $m2Response = $this->actingAs($ctx['advisor'])
            ->getJson("/api/v1/dealerships/{$dealershipId}/appointments?date=2026-11-02&technicianId={$an->id}");
        $m2Response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // M3: Repeated request with the same filter yields identical results
        $m3Response = $this->actingAs($ctx['advisor'])
            ->getJson("/api/v1/dealerships/{$dealershipId}/appointments?date=2026-11-02&technicianId={$an->id}");
        $m3Response->assertStatus(200);
        $this->assertSame($m2Response->json(), $m3Response->json());

        // M4: Advisor views an appointment (details + history)
        $m4Show = $this->actingAs($ctx['advisor'])->getJson("/api/v1/appointments/{$hungApptId}");
        $m4Show->assertStatus(200)
            ->assertHeader('ETag', '"1"')
            ->assertJsonPath('customer.phone', '0903123218');

        $m4History = $this->actingAs($ctx['advisor'])->getJson("/api/v1/appointments/{$hungApptId}/history");
        $m4History->assertStatus(200);
        $rawHistory = (string) $m4History->getContent();
        $this->assertStringNotContainsString('0903123218', $rawHistory);
        $this->assertStringNotContainsString('hung@example.test', $rawHistory);

        // M5: Customer Hùng views my bookings
        $m5Response = $this->actingAs($ctx['hungUser'])->getJson('/api/v1/me/appointments?scope=upcoming');
        $m5Response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
        $this->assertSame('hung@example.test', $ctx['hungUser']->email);

        // M6: Customer Khánh attempts to read Hùng's appointment ID -> 404 APPOINTMENT_NOT_FOUND
        $m6Response = $this->actingAs($ctx['khanhUser'])->getJson("/api/v1/appointments/{$hungApptId}");
        $m6Response->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND')
            ->assertJsonPath('title', "We couldn't find this appointment.");
    }
}
