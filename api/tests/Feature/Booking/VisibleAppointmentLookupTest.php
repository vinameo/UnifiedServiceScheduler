<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class VisibleAppointmentLookupTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', SubstituteBindings::class])->get('/api/v1/_test/appointments/{appointment}', function (Appointment $appointment) {
            return response()->json([
                'id' => $appointment->id,
                'reference' => $appointment->reference,
            ]);
        });
    }

    public function test_customer_can_find_own_appointment(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0010',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUser->id,
        ]);

        $repository = app(AppointmentRepositoryInterface::class);
        $found = $repository->findVisibleTo($customerUser, $appointment->id);

        $this->assertSame($appointment->id, $found->id);
        $this->assertTrue($found->relationLoaded('dealership'));
        $this->assertTrue($found->relationLoaded('customer'));
    }

    public function test_customer_cannot_find_another_customers_appointment(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $customerUserB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointmentA = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0011',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customerA->id,
            'vehicle_id' => $vehicleA->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUserA->id,
        ]);

        $repository = app(AppointmentRepositoryInterface::class);

        $this->expectException(AppointmentNotFound::class);
        $repository->findVisibleTo($customerUserB, $appointmentA->id);
    }

    public function test_staff_can_find_dealership_appointment(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $advisor = $world->advisor();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0012',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUser->id,
        ]);

        $repository = app(AppointmentRepositoryInterface::class);
        $found = $repository->findVisibleTo($advisor, $appointment->id);

        $this->assertSame($appointment->id, $found->id);
    }

    public function test_staff_cannot_find_appointment_in_another_dealership(): void
    {
        $world1 = World::saigonD7();
        $dealership2 = Dealership::factory()->create();
        $advisor2 = User::factory()->advisor($dealership2)->create();

        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world1->customerWithVehicle();
        $service = $world1->serviceType('OIL_CHANGE');
        $bay = $world1->bay('K1');
        $tech = $world1->technician('Trần Minh An');

        $appointmentD1 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0013',
            'dealership_id' => $world1->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUser->id,
        ]);

        $repository = app(AppointmentRepositoryInterface::class);

        $this->expectException(AppointmentNotFound::class);
        $repository->findVisibleTo($advisor2, $appointmentD1->id);
    }

    public function test_find_visible_to_rejects_malformed_uuid(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $repository = app(AppointmentRepositoryInterface::class);

        $this->expectException(AppointmentNotFound::class);
        $repository->findVisibleTo($advisor, 'not-a-uuid');
    }

    public function test_route_binding_resolves_and_enforces_scoping(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $customerUserB] = $world->customerWithVehicle();
        $dealership2 = Dealership::factory()->create();
        $advisor2 = User::factory()->advisor($dealership2)->create();

        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0014',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customerA->id,
            'vehicle_id' => $vehicleA->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUserA->id,
        ]);

        // 1. Owner customer can view
        $resA = $this->actingAs($customerUserA)->getJson("/api/v1/_test/appointments/{$appointment->id}");
        $resA->assertStatus(200)
            ->assertJson(['id' => $appointment->id]);

        // 2. Non-owner customer gets 404 APPOINTMENT_NOT_FOUND
        $resB = $this->actingAs($customerUserB)->getJson("/api/v1/_test/appointments/{$appointment->id}");
        $resB->assertStatus(404)
            ->assertJson([
                'status' => 404,
                'code' => 'APPOINTMENT_NOT_FOUND',
                'title' => "We couldn't find this appointment.",
            ]);

        // 3. Staff of another dealership gets 404 APPOINTMENT_NOT_FOUND
        $resForeignStaff = $this->actingAs($advisor2)->getJson("/api/v1/_test/appointments/{$appointment->id}");
        $resForeignStaff->assertStatus(404)
            ->assertJson([
                'status' => 404,
                'code' => 'APPOINTMENT_NOT_FOUND',
                'title' => "We couldn't find this appointment.",
            ]);

        // 4. Missing ID gets 404 APPOINTMENT_NOT_FOUND
        $missingUuid = (string) Str::uuid();
        $resMissing = $this->actingAs($customerUserA)->getJson("/api/v1/_test/appointments/{$missingUuid}");
        $resMissing->assertStatus(404)
            ->assertJson([
                'status' => 404,
                'code' => 'APPOINTMENT_NOT_FOUND',
                'title' => "We couldn't find this appointment.",
            ]);

        // 5. Malformed ID gets 404 APPOINTMENT_NOT_FOUND
        $resMalformed = $this->actingAs($customerUserA)->getJson('/api/v1/_test/appointments/not-a-uuid');
        $resMalformed->assertStatus(404)
            ->assertJson([
                'status' => 404,
                'code' => 'APPOINTMENT_NOT_FOUND',
                'title' => "We couldn't find this appointment.",
            ]);

        // 6. Non-owner customer response body and missing ID response body are identical except correlationId
        $bodyB = $resB->json();
        $bodyMissing = $resMissing->json();
        unset($bodyB['correlationId'], $bodyMissing['correlationId']);
        $this->assertSame($bodyMissing, $bodyB);

        // 7. Unauthenticated request gets 401 UNAUTHENTICATED
        $this->app['auth']->forgetGuards();
        $resUnauth = $this->getJson("/api/v1/_test/appointments/{$appointment->id}");
        $resUnauth->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'UNAUTHENTICATED',
                'title' => 'Please sign in.',
            ]);
    }
}
