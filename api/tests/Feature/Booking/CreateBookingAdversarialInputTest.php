<?php

namespace Tests\Feature\Booking;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class CreateBookingAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $unsetKeys
     */
    #[DataProvider('adversarialInputProvider')]
    public function test_TC_02_31_adversarial_input_table(
        string $actorRole,
        array $overrides,
        array $unsetKeys,
        int $expectedStatus,
        ?string $expectedCode,
    ): void {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        // Create a foreign dealership and foreign service for adversarial tests
        $foreignDealershipId = (string) Str::uuid();
        DB::table('dealerships')->insert([
            'id' => $foreignDealershipId,
            'code' => 'D2',
            'name' => 'Foreign Dealership D2',
            'phone' => '0281234567',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignServiceId = (string) Str::uuid();
        DB::table('service_types')->insert([
            'id' => $foreignServiceId,
            'dealership_id' => $foreignDealershipId,
            'code' => 'FOREIGN_OIL',
            'name' => 'Foreign Oil Change',
            'duration_minutes' => 60,
            'required_level' => 1,
            'required_bay_type' => 'STANDARD',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Second customer B
        ['customer' => $customerB] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $isCustomer = $actorRole === 'CUSTOMER';
            $actor = $isCustomer ? $customerUser : $advisor;

            $payload = [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:00+07:00',
            ];

            if ($isCustomer) {
                $payload['dealershipId'] = $world->dealership->id;
            }

            // Apply overrides
            foreach ($overrides as $k => $v) {
                if ($v === '__FOREIGN_DEALERSHIP__') {
                    $payload[$k] = $foreignDealershipId;
                } elseif ($v === '__FOREIGN_SERVICE__') {
                    $payload[$k] = $foreignServiceId;
                } elseif ($v === '__CUSTOMER_B__') {
                    $payload[$k] = $customerB->id;
                } else {
                    $payload[$k] = $v;
                }
            }

            // Unset keys
            foreach ($unsetKeys as $k) {
                unset($payload[$k]);
            }

            $response = $this->actingAs($actor)->postJson(
                '/api/v1/appointments',
                $payload,
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus($expectedStatus);
            if ($expectedCode !== null) {
                $response->assertJsonPath('code', $expectedCode);
            }

            if ($expectedStatus === 201) {
                if ($isCustomer) {
                    // AC-02.5: customer always gets booking assigned to themselves
                    $response->assertJsonPath('customer.id', $customer->id);
                } else {
                    // Advisor booking always belongs to advisor's dealership
                    $this->assertDatabaseHas('appointments', [
                        'id' => $response->json('id'),
                        'dealership_id' => $world->dealership->id,
                    ]);
                }
                $this->assertNoOverlappingAllocations();
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: list<string>, 3: int, 4: ?string}>
     */
    public static function adversarialInputProvider(): array
    {
        return [
            'desiredStart no offset' => [
                'ADVISOR',
                ['desiredStart' => '2026-11-02T09:00:00'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'desiredStart space separated no offset' => [
                'ADVISOR',
                ['desiredStart' => '2026-11-02 09:00'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'desiredStart 09:07 off 15-minute step' => [
                'ADVISOR',
                ['desiredStart' => '2026-11-02T09:07:00+07:00'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'desiredStart 02:00Z equivalent to 09:00+07' => [
                'ADVISOR',
                ['desiredStart' => '2026-11-02T02:00:00Z'],
                [],
                201,
                null,
            ],
            'desiredStart invalid calendar date Feb 30' => [
                'ADVISOR',
                ['desiredStart' => '2026-02-30T09:00:00+07:00'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'serviceTypeId non-uuid abc' => [
                'ADVISOR',
                ['serviceTypeId' => 'abc'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'serviceTypeId of foreign dealership' => [
                'ADVISOR',
                ['serviceTypeId' => '__FOREIGN_SERVICE__'],
                [],
                404,
                'SERVICE_TYPE_NOT_FOUND',
            ],
            'customer sends customer B id (ignored)' => [
                'CUSTOMER',
                ['customerId' => '__CUSTOMER_B__'],
                [],
                201,
                null,
            ],
            'customer sends no dealershipId' => [
                'CUSTOMER',
                [],
                ['dealershipId'],
                400,
                'VALIDATION_ERROR',
            ],
            'advisor sends foreign dealershipId (ignored)' => [
                'ADVISOR',
                ['dealershipId' => '__FOREIGN_DEALERSHIP__'],
                [],
                201,
                null,
            ],
            'mode HOLD before F10' => [
                'ADVISOR',
                ['mode' => 'HOLD'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'reminderOptIn yes string' => [
                'ADVISOR',
                ['reminderOptIn' => 'yes'],
                [],
                400,
                'VALIDATION_ERROR',
            ],
            'wrong service and past time -> 404 SERVICE_TYPE_NOT_FOUND' => [
                'ADVISOR',
                [
                    'serviceTypeId' => '__FOREIGN_SERVICE__',
                    'desiredStart' => '2026-11-02T07:00:00+07:00', // Past time (now is 08:00)
                ],
                [],
                404,
                'SERVICE_TYPE_NOT_FOUND',
            ],
            'wrong service and 09:07 off-step -> 400 VALIDATION_ERROR' => [
                'ADVISOR',
                [
                    'serviceTypeId' => '__FOREIGN_SERVICE__',
                    'desiredStart' => '2026-11-02T09:07:00+07:00', // Off-step
                ],
                [],
                400,
                'VALIDATION_ERROR',
            ],
        ];
    }
}
