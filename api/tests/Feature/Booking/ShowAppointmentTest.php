<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class ShowAppointmentTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_05_1_appointment_survives_restart(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $desiredStart = '2026-11-02T09:00:00+07:00';
            $key = (string) Str::uuid();

            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $oilChange->id,
                'desiredStart' => $desiredStart,
            ], [
                'Idempotency-Key' => $key,
            ]);

            $createResponse->assertStatus(201);
            $creationBody = $createResponse->json();
            $id = $creationBody['id'];

            // Simulate application restart
            $this->refreshApplication();

            // Re-authenticate and fetch through GET /api/v1/appointments/{id}
            $advisorUser = User::findOrFail($advisor->id);
            $getResponse = $this->actingAs($advisorUser)->getJson("/api/v1/appointments/{$id}");

            $getResponse->assertStatus(200)
                ->assertHeader('ETag', '"1"');

            $this->assertSame($creationBody, $getResponse->json());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_05_3_unknown_id(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAs($advisor)->getJson("/api/v1/appointments/{$randomUuid}");

        $response->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND')
            ->assertJsonPath('title', "We couldn't find this appointment.");
    }

    public function test_AC_00_8_customer_cannot_see_another_customers_appointment(): void
    {
        $world = World::saigonD7();
        ['user' => $userA] = $world->customerWithVehicle();
        ['user' => $userB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointmentB = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0055',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customerB->id,
            'vehicle_id' => $vehicleB->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $userB->id,
        ]);

        $unknownUuid = (string) Str::uuid();
        $unknownResponse = $this->actingAs($userA)->getJson("/api/v1/appointments/{$unknownUuid}");
        $unknownResponse->assertStatus(404)->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        $responseA = $this->actingAs($userA)->getJson("/api/v1/appointments/{$appointmentB->id}");
        $responseA->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND')
            ->assertJsonPath('title', "We couldn't find this appointment.");

        // Body is identical to missing id except correlationId
        $unknownJson = $unknownResponse->json();
        $responseAJson = $responseA->json();
        unset($unknownJson['correlationId'], $responseAJson['correlationId']);
        $this->assertSame($unknownJson, $responseAJson);
    }

    public function test_AC_05_5_staff_of_another_dealership_gets_404(): void
    {
        $world1 = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world1->customerWithVehicle();
        $service = $world1->serviceType('OIL_CHANGE');
        $bay = $world1->bay('K1');
        $tech = $world1->technician('Trần Minh An');

        $appointmentD1 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0056',
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

        $dealership2 = Dealership::factory()->create();
        $advisorD2 = User::factory()->advisor($dealership2)->create();

        $response = $this->actingAs($advisorD2)->getJson("/api/v1/appointments/{$appointmentD1->id}");
        $response->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND')
            ->assertJsonPath('title', "We couldn't find this appointment.");
    }

    public function test_D_05_9_every_appointment_route_is_scoped(): void
    {
        $world1 = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world1->customerWithVehicle();
        $service = $world1->serviceType('OIL_CHANGE');
        $bay = $world1->bay('K1');
        $tech = $world1->technician('Trần Minh An');

        $appointmentD1 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0057',
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

        $dealership2 = Dealership::factory()->create();
        $advisorD2 = User::factory()->advisor($dealership2)->create();

        $appointmentRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), '{appointment}') && ! str_contains($route->uri(), '_test'));

        $this->assertNotEmpty($appointmentRoutes, 'At least one {appointment} route should exist.');

        foreach ($appointmentRoutes as $route) {
            $uri = str_replace('{appointment}', $appointmentD1->id, $route->uri());
            $methods = array_diff($route->methods(), ['HEAD']);
            foreach ($methods as $method) {
                $response = $this->actingAs($advisorD2)->json($method, "/{$uri}");
                $response->assertStatus(404)
                    ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');
            }
        }
    }
}
