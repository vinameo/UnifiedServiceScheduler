<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Events\IdempotentReplayed;
use App\Models\Appointment;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_04_3_same_key_same_body_replays(): void
    {
        Event::fake([IdempotentReplayed::class]);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $key = (string) Str::uuid();

        $payload = [
            'dealershipId' => $world->dealership->id,
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'start' => $now->toIso8601String(),
        ];

        // 1. Initial request with key K
        $response1 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', $payload, [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response1->assertStatus(201)
            ->assertHeader('ETag', '"1"');

        $apptId = $response1->json('id');
        $location = $response1->headers->get('Location');
        $etag = $response1->headers->get('ETag');

        $this->assertNotNull($apptId);
        $this->assertNotNull($location);
        $this->assertNotNull($etag);

        // 2. Identical request with key K
        $response2 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', $payload, [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response2->assertStatus(201)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertHeader('Location', $location)
            ->assertHeader('ETag', $etag)
            ->assertJsonPath('id', $apptId);

        // Exactly 1 booking created
        $this->assertSame(1, DB::table('appointments')->count());

        // IdempotentReplayed dispatched once
        Event::assertDispatched(IdempotentReplayed::class, 1);
    }

    public function test_AC_04_4_same_key_different_body_is_422(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $v1] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $v2 = Vehicle::create([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'vin' => '1HGCR2F83HA000002',
            'licence_plate' => '51A-999.99',
            'plate_key' => '51A99999',
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2022,
        ]);

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $key = (string) Str::uuid();

        // 1. Request with vehicle v1
        $response1 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $v1->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response1->assertStatus(201);

        // 2. Request with vehicle v2 using the same key K
        $response2 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $v2->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_MISMATCH');

        // No new booking
        $this->assertSame(1, DB::table('appointments')->count());
    }

    public function test_AC_04_5_saved_409_is_replayed(): void
    {
        Event::fake([IdempotentReplayed::class]);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');

        // 1. Disable all bays so candidate finder finds 0 availability -> 409
        DB::table('bays')->where('dealership_id', $world->dealership->id)->update(['active' => false]);

        $key = (string) Str::uuid();

        // 2. User requests appointment -> returns 409 NO_AVAILABILITY
        $response409 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response409->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        // 3. Free the slots by re-enabling bays
        DB::table('bays')->where('dealership_id', $world->dealership->id)->update(['active' => true]);

        // 4. Send request again with key K
        $replayed409 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        // Still the saved 409 (not 201)
        $replayed409->assertStatus(409)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        // 0 appointments exist in DB
        $this->assertSame(0, DB::table('appointments')->count());
        Event::assertDispatched(IdempotentReplayed::class, 1);
    }

    public function test_AC_04_16_keys_are_per_user(): void
    {
        $world = World::saigonD7();
        ['user' => $userA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $userB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $nowA = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $nowB = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $sharedKey = (string) Str::uuid();

        // User A uses sharedKey
        $responseA = $this->actingAs($userA)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customerA->id,
                'vehicleId' => $vehicleA->id,
                'serviceTypeId' => $service->id,
                'start' => $nowA->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $sharedKey,
            ]);
        $responseA->assertStatus(201);
        $idA = $responseA->json('id');

        // User B sends sharedKey with B's body
        $responseB = $this->actingAs($userB)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customerB->id,
                'vehicleId' => $vehicleB->id,
                'serviceTypeId' => $service->id,
                'start' => $nowB->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $sharedKey,
            ]);
        $responseB->assertStatus(201);
        $idB = $responseB->json('id');

        $this->assertNotSame($idA, $idB);
        $this->assertSame(2, DB::table('appointments')->count());
    }

    public function test_D_04_14_hash_is_canonical_and_path_bound(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $key = (string) Str::uuid();

        // 1. K used with body {"a": 1, "b": 2} at /_testing/echo
        $response1 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/echo', [
                'a' => 1,
                'b' => 2,
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);
        $response1->assertStatus(200)
            ->assertJsonPath('count', 1);

        // 2. Send K with {"b": 2, "a": 1} at /_testing/echo -> saved answer replayed
        $response2 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/echo', [
                'b' => 2,
                'a' => 1,
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);
        $response2->assertStatus(200)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('count', 1);

        // 3. Send K at /_testing/appointments -> 422 MISMATCH
        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $response3 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response3->assertStatus(422)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_MISMATCH');
    }

    public function test_AC_04_17_server_errors_are_not_saved(): void
    {
        $world = World::saigonD7();
        ['user' => $user] = $world->customerWithVehicle();
        $key = (string) Str::uuid();

        $initialCount = (int) DB::table('_testing_echo_counter')->where('id', 1)->value('count');

        // 1. Request with ?fail=500 -> 500 returned
        $response500 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/echo?fail=500', ['foo' => 'bar'], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);
        $response500->assertStatus(500);

        // Assert row was deleted
        $this->assertSame(0, DB::table('idempotency_keys')->where('key', $key)->count());

        // 2. Send key again without fail
        $response200 = $this->actingAs($user)
            ->postJson('/api/v1/_testing/echo', ['foo' => 'bar'], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);
        $response200->assertStatus(200)
            ->assertJsonPath('count', $initialCount + 1);

        // Row is now COMPLETED
        $this->assertSame(1, DB::table('idempotency_keys')->where('key', $key)->where('status', 'COMPLETED')->count());
    }

    public function test_AC_04_18_stale_lock_is_taken_over(): void
    {
        $world = World::saigonD7();
        ['user' => $user] = $world->customerWithVehicle();
        $key = (string) Str::uuid();
        $now = CarbonImmutable::now();

        // 1. Insert row by hand as IN_PROGRESS with locked_until = now - 1s (stale lock)
        $rowId = (string) Str::uuid();
        $method = 'POST';
        $path = '/api/v1/_testing/echo';
        $rawPayload = "POST\n/api/v1/_testing/echo\n".json_encode(['action' => 'test']);
        $requestHash = hash('sha256', $rawPayload);

        DB::table('idempotency_keys')->insert([
            'id' => $rowId,
            'user_id' => $user->id,
            'key' => $key,
            'method' => $method,
            'path' => $path,
            'request_hash' => $requestHash,
            'status' => 'IN_PROGRESS',
            'locked_until' => $now->subSeconds(1),
            'created_at' => $now->subMinute(),
            'expires_at' => $now->addHours(24),
        ]);

        // 2. Send request with key K -> runs and transitions row to COMPLETED
        $response = $this->actingAs($user)
            ->postJson('/api/v1/_testing/echo', ['action' => 'test'], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response->assertStatus(200);

        $row = DB::table('idempotency_keys')->where('key', $key)->first();
        $this->assertNotNull($row);
        $this->assertSame('COMPLETED', $row->status);
        $this->assertSame(200, $row->response_status);
    }

    public function test_AC_04_19_expired_keys_are_pruned_and_ignored(): void
    {
        $world = World::saigonD7();
        ['user' => $user] = $world->customerWithVehicle();
        $key = (string) Str::uuid();
        $now = CarbonImmutable::now();

        // 1. Insert row K with expires_at one hour ago
        $rowId = (string) Str::uuid();
        $method = 'POST';
        $path = '/api/v1/_testing/echo';
        $rawPayload = "POST\n/api/v1/_testing/echo\n".json_encode(['action' => 'prune_test']);
        $requestHash = hash('sha256', $rawPayload);

        DB::table('idempotency_keys')->insert([
            'id' => $rowId,
            'user_id' => $user->id,
            'key' => $key,
            'method' => $method,
            'path' => $path,
            'request_hash' => $requestHash,
            'status' => 'COMPLETED',
            'locked_until' => $now->subHours(2),
            'response_status' => 200,
            'response_headers' => json_encode(['Content-Type' => 'application/json']),
            'response_body' => json_encode(['old' => true]),
            'created_at' => $now->subHours(25),
            'expires_at' => $now->subHour(),
        ]);

        $this->assertSame(1, DB::table('idempotency_keys')->where('key', $key)->count());

        // 2. Run artisan idempotency:prune
        $command = $this->artisan('idempotency:prune');
        if ($command instanceof PendingCommand) {
            $command->assertSuccessful();
        } else {
            $this->assertSame(0, $command);
        }

        // Row is deleted
        $this->assertSame(0, DB::table('idempotency_keys')->where('key', $key)->count());

        // 3. Send K again -> runs as new
        $response = $this->actingAs($user)
            ->postJson('/api/v1/_testing/echo', ['action' => 'prune_test'], [
                'Accept' => 'application/json',
                'Idempotency-Key' => $key,
            ]);

        $response->assertStatus(200)
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(1, DB::table('idempotency_keys')->where('key', $key)->where('status', 'COMPLETED')->count());
    }
}
