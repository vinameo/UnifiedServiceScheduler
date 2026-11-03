<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AppointmentPolicyTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_policy_allows_customer_to_view_own_appointment(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0001',
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

        $this->assertTrue(Gate::forUser($customerUser)->allows('view', $appointment));
    }

    public function test_policy_denies_customer_from_viewing_another_customers_appointment(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $customerUserB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointmentA = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0002',
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

        $this->assertFalse(Gate::forUser($customerUserB)->allows('view', $appointmentA));
    }

    public function test_policy_allows_staff_to_view_appointment_in_own_dealership(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $advisor = $world->advisor();
        $service = $world->serviceType('OIL_CHANGE');
        $bay = $world->bay('K1');
        $tech = $world->technician('Trần Minh An');

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0003',
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

        $this->assertTrue(Gate::forUser($advisor)->allows('view', $appointment));
    }

    public function test_policy_denies_staff_from_viewing_appointment_in_another_dealership(): void
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
            'reference' => 'APT-20261102-0004',
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

        $this->assertFalse(Gate::forUser($advisor2)->allows('view', $appointmentD1));
    }
}
