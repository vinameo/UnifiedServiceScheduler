<?php

namespace Tests\Feature\Monitoring;

use App\Domain\Booking\CancelReason;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Logging\CapturesLogs;
use Tests\Support\World;
use Tests\TestCase;

class LogRedactionTest extends TestCase
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
     * TC-08-05 (AC-08.5, INV-08.1; Integration):
     * given TestHandler;
     * when Sign-up (email, phone), failed sign-in, booking, cancel (F07), a deliberate 500 (test route);
     * then Every record is json_encode-able; each has correlationId;
     * none matches an email regex, \b0\d{9}\b, "password", XSRF, laravel_session.
     */
    public function test_AC_08_5_logs_have_no_personal_data(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // 1. Sign-up with email and phone
        $signUpResponse = $this->postJson('/api/v1/auth/customer/sign-up', [
            'email' => 'log_redaction_customer@example.com',
            'password' => 'SecretPassword123!',
            'passwordConfirmation' => 'SecretPassword123!',
            'fullName' => 'Redaction Test Customer',
            'phone' => '0903123218',
        ]);
        $signUpResponse->assertStatus(201);

        // 2. Failed sign-in
        $signInResponse = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'log_redaction_customer@example.com',
            'password' => 'WrongPassword999!',
        ]);
        $signInResponse->assertStatus(401);

        // 3. Booking
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Corolla', 'plate_key' => '51H88888', 'licence_plate' => '51H-888.88']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');
        $tomorrow = '2026-11-03T09:00:00+07:00';

        $bookResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $serviceType->id,
            'desiredStart' => $tomorrow,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $bookResponse->assertStatus(201);
        $appointmentId = $bookResponse->json('id');

        // 4. Cancel (F07)
        $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
            'reason' => CancelReason::CUSTOMER_BUSY->value,
            'note' => 'Customer called to cancel due to schedule conflict',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
            'If-Match' => '"1"',
        ]);
        $cancelResponse->assertStatus(200);

        // 5. Deliberate 500 (test route throwing exception with personal data)
        $failResponse = $this->getJson('/api/v1/_testing/fail');
        $failResponse->assertStatus(500);

        // Assert all captured log records
        $records = $this->testLogHandler->getRecords();
        $this->assertNotEmpty($records, 'Expected log records to be captured');

        $formatter = new JsonFormatter;
        $emailPattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+/';
        $phonePattern = '/\b0\d{9}\b/';

        foreach ($records as $index => $record) {
            // 1. Every record is json_encode-able
            $json = $formatter->format($record);
            $this->assertNotEmpty($json, "Record at index {$index} formatted to empty string");

            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded, "Record at index {$index} is not valid JSON: {$json}");

            // 2. Each has correlationId
            $this->assertArrayHasKey(
                'correlationId',
                $record->context,
                "Record at index {$index} ({$record->message}) lacks correlationId in context",
            );
            $this->assertNotEmpty(
                $record->context['correlationId'],
                "Record at index {$index} ({$record->message}) has empty correlationId",
            );

            // 3. None matches email regex
            $this->assertDoesNotMatchRegularExpression(
                $emailPattern,
                $json,
                "Record at index {$index} contains email: {$json}",
            );

            // 4. None matches \b0\d{9}\b
            $this->assertDoesNotMatchRegularExpression(
                $phonePattern,
                $json,
                "Record at index {$index} contains phone: {$json}",
            );

            // 5. None matches "password"
            $this->assertStringNotContainsString(
                '"password"',
                $json,
                "Record at index {$index} contains \"password\": {$json}",
            );

            // 6. None matches XSRF
            $this->assertStringNotContainsString(
                'XSRF',
                $json,
                "Record at index {$index} contains XSRF: {$json}",
            );

            // 7. None matches laravel_session
            $this->assertStringNotContainsString(
                'laravel_session',
                $json,
                "Record at index {$index} contains laravel_session: {$json}",
            );
        }
    }
}
