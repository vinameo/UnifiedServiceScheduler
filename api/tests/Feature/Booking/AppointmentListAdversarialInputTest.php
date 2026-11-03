<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use App\Support\Pagination\CursorPaginator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AppointmentListAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * @param  array<string, mixed>  $queryParams
     */
    #[DataProvider('adversarialInputProvider')]
    public function test_TC_05_11_adversarial_input_table(
        string $endpointKey,
        string $uriTemplate,
        array $queryParams,
        string $actorRole,
        int $expectedStatus,
        ?string $expectedCode,
        ?string $checkType = null,
    ): void {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Dealership 1
            $world1 = World::saigonD7();
            $advisor1 = $world1->advisor();
            ['user' => $userA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world1->customerWithVehicle();
            $service1 = $world1->serviceType('OIL_CHANGE');
            $bay1 = $world1->bay('K1');
            $tech1 = $world1->technician('Trần Minh An');

            $appointmentA = Appointment::create([
                'id' => (string) Str::uuid(),
                'reference' => 'APT-20261102-0001',
                'dealership_id' => $world1->dealership->id,
                'customer_id' => $customerA->id,
                'vehicle_id' => $vehicleA->id,
                'service_type_id' => $service1->id,
                'technician_id' => $tech1->id,
                'bay_id' => $bay1->id,
                'status' => AppointmentStatus::CONFIRMED,
                'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+07:00'),
                'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+07:00'),
                'version' => 1,
                'source' => 'CUSTOMER',
                'reminder_opt_in' => false,
                'created_by_user_id' => $userA->id,
            ]);

            // Dealership 2
            $dealership2 = Dealership::factory()->create();
            $world2 = World::forDealership($dealership2);
            $advisor2 = $world2->advisor();
            ['user' => $userB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world2->customerWithVehicle();
            $service2 = $world2->serviceType('OIL_CHANGE');
            $bay2 = $world2->bay('K1');
            $tech2 = $world2->technician('Trần Minh An');

            $appointmentB = Appointment::create([
                'id' => (string) Str::uuid(),
                'reference' => 'APT-20261102-0002',
                'dealership_id' => $dealership2->id,
                'customer_id' => $customerB->id,
                'vehicle_id' => $vehicleB->id,
                'service_type_id' => $service2->id,
                'technician_id' => $tech2->id,
                'bay_id' => $bay2->id,
                'status' => AppointmentStatus::CONFIRMED,
                'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+07:00'),
                'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+07:00'),
                'version' => 1,
                'source' => 'CUSTOMER',
                'reminder_opt_in' => false,
                'created_by_user_id' => $userB->id,
            ]);

            // Cursor from another endpoint (e.g. outbox/history with occurred_at key instead of start_at)
            $foreignCursor = CursorPaginator::encode([
                'occurred_at' => '2026-11-02T08:00:00.000000+07:00',
                'id' => (string) Str::uuid(),
            ]);

            // Select actor
            $actor = match ($actorRole) {
                'ADVISOR' => $advisor1,
                'ADVISOR_D2' => $advisor2,
                'CUSTOMER_A' => $userA,
                'CUSTOMER_B' => $userB,
                default => throw new \InvalidArgumentException("Unknown actor: {$actorRole}"),
            };

            // Replace URI placeholders
            $uri = str_replace(
                ['{dealership}', '{appointment_a}', '{appointment_b}'],
                [$world1->dealership->id, $appointmentA->id, $appointmentB->id],
                $uriTemplate
            );

            // Replace parameter placeholders
            $params = [];
            foreach ($queryParams as $k => $v) {
                if ($v === '__FOREIGN_TECH_ID__') {
                    $params[$k] = $tech2->id;
                } elseif ($v === '__CUSTOMER_B_ID__') {
                    $params[$k] = $customerB->id;
                } elseif ($v === '__FOREIGN_CURSOR__') {
                    $params[$k] = $foreignCursor;
                } else {
                    $params[$k] = $v;
                }
            }

            $url = $uri.(! empty($params) ? '?'.http_build_query($params) : '');

            $response = $this->actingAs($actor)->getJson($url);

            $response->assertStatus($expectedStatus);

            if ($expectedCode !== null) {
                $response->assertJsonPath('code', $expectedCode);
            }

            // Custom checks per row behavior
            if ($checkType === 'identical_to_missing_id') {
                $missingUuid = (string) Str::uuid();
                $missingResponse = $this->actingAs($actor)->getJson("/api/v1/appointments/{$missingUuid}");
                $missingResponse->assertStatus(404)->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

                $missingJson = $missingResponse->json();
                $adversarialJson = $response->json();
                unset($missingJson['correlationId'], $adversarialJson['correlationId']);
                $this->assertSame($missingJson, $adversarialJson);
            } elseif ($checkType === 'empty_data') {
                $response->assertJsonCount(0, 'data')
                    ->assertJsonPath('meta.total', 0);
            } elseif ($checkType === 'ignored_customer_id') {
                $response->assertJsonPath('meta.total', 1)
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.customer.id', $customerA->id)
                    ->assertJsonPath('data.0.id', $appointmentA->id);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array<string, array{
     *     0: string,
     *     1: string,
     *     2: array<string, mixed>,
     *     3: string,
     *     4: int,
     *     5: ?string,
     *     6: ?string
     * }>
     */
    public static function adversarialInputProvider(): array
    {
        return [
            // API-05.1: {appointment} = abc -> 404 APPOINTMENT_NOT_FOUND
            '05.1: appointment = abc (advisor)' => [
                '05.1',
                '/api/v1/appointments/abc',
                [],
                'ADVISOR',
                404,
                'APPOINTMENT_NOT_FOUND',
                null,
            ],
            '05.1: appointment = abc (customer)' => [
                '05.1',
                '/api/v1/appointments/abc',
                [],
                'CUSTOMER_A',
                404,
                'APPOINTMENT_NOT_FOUND',
                null,
            ],

            // API-05.1: Another customer's / another dealership's booking -> 404 APPOINTMENT_NOT_FOUND
            // (body identical to a missing id except correlationId)
            '05.1: another customer booking' => [
                '05.1',
                '/api/v1/appointments/{appointment_b}',
                [],
                'CUSTOMER_A',
                404,
                'APPOINTMENT_NOT_FOUND',
                'identical_to_missing_id',
            ],
            '05.1: another dealership booking' => [
                '05.1',
                '/api/v1/appointments/{appointment_b}',
                [],
                'ADVISOR',
                404,
                'APPOINTMENT_NOT_FOUND',
                'identical_to_missing_id',
            ],

            // API-05.3: date=2026-13-01, date=02/11/2026 -> 400 VALIDATION_ERROR
            '05.3: date=2026-13-01' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-13-01'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.3: date=02/11/2026' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '02/11/2026'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],

            // API-05.3: status=FOO -> 400 VALIDATION_ERROR
            '05.3: status=FOO' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'status' => 'FOO'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],

            // API-05.3: status=CONFIRMED, (empty item / trailing comma) -> 400 VALIDATION_ERROR
            '05.3: status=CONFIRMED, (trailing comma)' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'status' => 'CONFIRMED,'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],

            // API-05.3: limit=0, limit=201, limit=abc -> 400 VALIDATION_ERROR
            '05.3: limit=0' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'limit' => '0'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.3: limit=201' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'limit' => '201'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.3: limit=abc' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'limit' => 'abc'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],

            // API-05.3: cursor=xyz, cursor from another endpoint -> 400 VALIDATION_ERROR
            '05.3: cursor=xyz' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'cursor' => 'xyz'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.3: cursor from another endpoint' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'cursor' => '__FOREIGN_CURSOR__'],
                'ADVISOR',
                400,
                'VALIDATION_ERROR',
                null,
            ],

            // API-05.3: technicianId of another dealership -> 200, data: []
            '05.3: technicianId of another dealership' => [
                '05.3',
                '/api/v1/dealerships/{dealership}/appointments',
                ['date' => '2026-11-02', 'technicianId' => '__FOREIGN_TECH_ID__'],
                'ADVISOR',
                200,
                null,
                'empty_data',
            ],

            // API-05.4: scope=all -> 400 VALIDATION_ERROR
            '05.4: scope=all' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'all'],
                'CUSTOMER_A',
                400,
                'VALIDATION_ERROR',
                null,
            ],

            // API-05.4: customerId=<B> -> Ignored, returns only authenticated customer's appointments
            '05.4: customerId=<B>' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'upcoming', 'customerId' => '__CUSTOMER_B_ID__'],
                'CUSTOMER_A',
                200,
                null,
                'ignored_customer_id',
            ],

            // API-05.4 list limits and cursor adversarial inputs
            '05.4: limit=0' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'upcoming', 'limit' => '0'],
                'CUSTOMER_A',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.4: limit=201' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'upcoming', 'limit' => '201'],
                'CUSTOMER_A',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.4: limit=abc' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'upcoming', 'limit' => 'abc'],
                'CUSTOMER_A',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.4: cursor=xyz' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'upcoming', 'cursor' => 'xyz'],
                'CUSTOMER_A',
                400,
                'VALIDATION_ERROR',
                null,
            ],
            '05.4: cursor from another endpoint' => [
                '05.4',
                '/api/v1/me/appointments',
                ['scope' => 'upcoming', 'cursor' => '__FOREIGN_CURSOR__'],
                'CUSTOMER_A',
                400,
                'VALIDATION_ERROR',
                null,
            ],
        ];
    }

    /**
     * Dedicated test for TC-05-20:
     * D2 advisor calls every {appointment} route and receives 404 APPOINTMENT_NOT_FOUND,
     * with valid write headers, and nothing is written.
     */
    public function test_TC_05_20_every_appointment_route_is_scoped(): void
    {
        $world1 = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world1->customerWithVehicle();
        $service = $world1->serviceType('OIL_CHANGE');
        $bay = $world1->bay('K1');
        $tech = $world1->technician('Trần Minh An');

        $appointmentD1 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0099',
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

        $freshBefore = $appointmentD1->fresh();
        $this->assertNotNull($freshBefore);
        $initialUpdatedAt = $freshBefore->updated_at;
        $this->assertNotNull($initialUpdatedAt);

        $dealership2 = Dealership::factory()->create();
        $advisorD2 = User::factory()->advisor($dealership2)->create();

        $appointmentRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), '{appointment}') && ! str_contains($route->uri(), '_test'));

        $this->assertNotEmpty($appointmentRoutes, 'At least one {appointment} route should exist.');

        foreach ($appointmentRoutes as $route) {
            $uri = str_replace('{appointment}', $appointmentD1->id, $route->uri());
            $methods = array_diff($route->methods(), ['HEAD']);
            foreach ($methods as $method) {
                $response = $this->actingAs($advisorD2)->json(
                    $method,
                    "/{$uri}",
                    [],
                    [
                        'Idempotency-Key' => (string) Str::uuid(),
                        'If-Match' => '"1"',
                    ]
                );
                $response->assertStatus(404)
                    ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');
            }
        }

        $fresh = $appointmentD1->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(1, $fresh->version);
        $this->assertNotNull($fresh->updated_at);
        $this->assertTrue($fresh->updated_at->eq($initialUpdatedAt));
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointmentD1->id]);
        $this->assertNoOverlappingAllocations();
    }
}
