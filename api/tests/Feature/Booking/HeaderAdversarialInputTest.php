<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class HeaderAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    #[DataProvider('headerAdversarialInputProvider')]
    public function test_header_adversarial_input(
        ?string $ifMatchHeader,
        ?string $idempotencyKeyHeader,
        int $expectedStatus,
        string $expectedCode,
    ): void {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        // Appointment at version 1
        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261122-0077',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $headers = [
            'Accept' => 'application/json',
        ];

        if ($ifMatchHeader !== null) {
            $headers['If-Match'] = $ifMatchHeader;
        }

        if ($idempotencyKeyHeader !== null) {
            $headers['Idempotency-Key'] = $idempotencyKeyHeader;
        }

        $response = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], $headers);

        $response->assertStatus($expectedStatus)
            ->assertJsonPath('code', $expectedCode);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: int, 3: string}>
     */
    public static function headerAdversarialInputProvider(): array
    {
        $validKey = 'a0000000-0000-0000-0000-000000000001';

        return [
            'If-Match: *' => ['*', $validKey, 400, 'VALIDATION_ERROR'],
            'If-Match: W/"1"' => ['W/"1"', $validKey, 400, 'VALIDATION_ERROR'],
            'If-Match: "0"' => ['"0"', $validKey, 400, 'VALIDATION_ERROR'],
            'If-Match: "-1"' => ['"-1"', $validKey, 400, 'VALIDATION_ERROR'],
            'If-Match: "1.5"' => ['"1.5"', $validKey, 400, 'VALIDATION_ERROR'],
            'If-Match: "abc"' => ['"abc"', $validKey, 400, 'VALIDATION_ERROR'],
            'If-Match: "999"' => ['"999"', $validKey, 412, 'APPOINTMENT_MODIFIED'],
            'Idempotency-Key: missing' => ['"1"', null, 400, 'IDEMPOTENCY_KEY_REQUIRED'],
            'Idempotency-Key: abc' => ['"1"', 'abc', 400, 'VALIDATION_ERROR'],
        ];
    }
}
