<?php

namespace Tests\Feature\Monitoring;

use App\Domain\Monitoring\Services\ApiEventServiceInterface;
use App\Models\ApiEvent;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Logging\CapturesLogs;
use Tests\Support\World;
use Tests\TestCase;

class ApiEventsTest extends TestCase
{
    use AssertsNoOverlap;
    use CapturesLogs;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCapturesLogs();
    }

    /**
     * TC-08-16 (INV-08.7; Integration):
     * given A test route throws an exception whose message holds an email;
     * when Call it;
     * then api_events.detail has no email.
     */
    public function test_INV_08_7_detail_is_redacted(): void
    {
        $response = $this->getJson('/api/v1/_testing/fail');
        $response->assertStatus(500);

        $correlationId = $response->headers->get('X-Correlation-Id');
        $this->assertNotNull($correlationId);

        /** @var ApiEvent|null $event */
        $event = ApiEvent::query()
            ->where('correlation_id', $correlationId)
            ->first();

        $this->assertNotNull($event, 'Expected an api_events row for status 500 error');
        $this->assertSame(500, $event->status);
        $this->assertSame('error', $event->outcome);
        $this->assertSame('GET api/v1/_testing/fail', $event->route);

        $detailJson = (string) json_encode($event->detail);
        $this->assertStringNotContainsString('user@example.com', $detailJson);
        $this->assertStringNotContainsString('0903123218', $detailJson);
        $this->assertStringContainsString('[email]', $detailJson);
        $this->assertStringContainsString('[phone]', $detailJson);
        $this->assertSame('RuntimeException', $event->detail['class'] ?? null);
    }

    /**
     * Proves that successful POST /appointments requests are recorded in api_events.
     */
    public function test_records_successful_booking_to_api_events(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Camry', 'plate_key' => '51H11111', 'licence_plate' => '51H-111.11']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        $correlationId = 'test-corr-id-'.Str::uuid();
        $response = $this->actingAs($advisor)->withHeader('X-Correlation-Id', $correlationId)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(201);
        $appointmentId = $response->json('id');

        /** @var ApiEvent|null $event */
        $event = ApiEvent::query()
            ->where('correlation_id', $correlationId)
            ->first();

        $this->assertNotNull($event, 'Expected an api_events row for 201 POST appointments');
        $this->assertSame(201, $event->status);
        $this->assertSame('confirmed', $event->outcome);
        $this->assertSame('POST api/v1/appointments', $event->route);
        $this->assertSame($world->dealership->id, $event->dealership_id);
        $this->assertSame($appointmentId, $event->appointment_id);
        $this->assertFalse($event->idempotent_replay);
    }

    /**
     * Proves that 409 NO_AVAILABILITY booking requests store structured detail.
     */
    public function test_records_no_availability_to_api_events_with_structured_detail(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Corolla', 'plate_key' => '51H22222', 'licence_plate' => '51H-222.22']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        // Disable bays to force NO_AVAILABILITY
        DB::table('bays')->where('dealership_id', $world->dealership->id)->update(['active' => false]);

        $correlationId = 'test-409-'.Str::uuid();
        $response = $this->actingAs($advisor)->withHeader('X-Correlation-Id', $correlationId)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        /** @var ApiEvent|null $event */
        $event = ApiEvent::query()
            ->where('correlation_id', $correlationId)
            ->first();

        $this->assertNotNull($event, 'Expected an api_events row for 409 response');
        $this->assertSame(409, $event->status);
        $this->assertSame('NO_AVAILABILITY', $event->code);
        $this->assertSame('no_availability', $event->outcome);
        $this->assertArrayHasKey('reason', $event->detail);
        $this->assertArrayHasKey('requestedStart', $event->detail);
        $this->assertArrayHasKey('requestedEnd', $event->detail);
        $this->assertArrayHasKey('alternativesCount', $event->detail);
    }

    /**
     * Proves that non-booking 4xx errors are recorded to api_events.
     */
    public function test_records_non_booking_4xx_to_api_events(): void
    {
        $correlationId = 'test-401-'.Str::uuid();
        $response = $this->withHeader('X-Correlation-Id', $correlationId)->getJson('/api/v1/me');
        $response->assertStatus(401);

        /** @var ApiEvent|null $event */
        $event = ApiEvent::query()
            ->where('correlation_id', $correlationId)
            ->first();

        $this->assertNotNull($event, 'Expected an api_events row for 401 UNAUTHENTICATED');
        $this->assertSame(401, $event->status);
        $this->assertSame('UNAUTHENTICATED', $event->code);
        $this->assertSame('validation_error', $event->outcome);
    }

    /**
     * Proves that 200 GET requests (non-booking, non-error) are NOT stored in api_events.
     */
    public function test_successful_read_requests_are_not_recorded(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $correlationId = 'test-200-'.Str::uuid();
        $response = $this->actingAs($advisor)
            ->withHeader('X-Correlation-Id', $correlationId)
            ->getJson('/api/v1/dealerships');
        $response->assertStatus(200);

        $event = ApiEvent::query()
            ->where('correlation_id', $correlationId)
            ->first();

        $this->assertNull($event, '200 GET requests should not be recorded in api_events');
    }

    /**
     * INV-08.4: Proves that failures when recording to api_events never break the client response.
     */
    public function test_INV_08_4_recording_failure_does_not_break_request(): void
    {
        $mockService = new class implements ApiEventServiceInterface
        {
            public function record(array $data): ApiEvent
            {
                throw new Exception('Database connection failed during event logging');
            }
        };

        $this->app->instance(ApiEventServiceInterface::class, $mockService);

        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(401);

        $records = $this->testLogHandler->getRecords();
        $warnings = array_filter($records, fn ($r) => str_contains($r->message, 'Failed to record API event'));
        $this->assertNotEmpty($warnings, 'Expected warning log when api_events recording fails');
    }
}
