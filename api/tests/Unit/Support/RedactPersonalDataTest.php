<?php

namespace Tests\Unit\Support;

use App\Support\Logging\RedactPersonalData;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RedactPersonalDataTest extends TestCase
{
    /**
     * TC-08-14 (D-08.2, R-08.4; Unit):
     * when RedactPersonalData on:
     *   {"user":{"email":"a@b.c"}},
     *   message "call 0903 123 218";
     *   message "call +84 903 123 218";
     *   {"appointmentId":"01935512-7712-7abc-9def-0123456789ab"};
     *   message "booked APT-20261102-0042 at 2026-11-02T09:00:00+07:00";
     *   {"Authorization":"x"};
     * then email dropped; "call [phone]" twice; the UUID unchanged;
     * the reference and the time unchanged; Authorization key dropped.
     */
    public function test_TC_08_14_redacts_personal_data_and_preserves_identifiers(): void
    {
        $processor = new RedactPersonalData;

        // 1. Phone numbers in message
        $recordPhone1 = $this->makeRecord('call 0903 123 218');
        $processedPhone1 = $processor($recordPhone1);
        $this->assertSame('call [phone]', $processedPhone1->message);

        $recordPhone2 = $this->makeRecord('call +84 903 123 218');
        $processedPhone2 = $processor($recordPhone2);
        $this->assertSame('call [phone]', $processedPhone2->message);

        // 2. Reference and ISO 8601 timestamp in message remain unchanged
        $recordBooking = $this->makeRecord('booked APT-20261102-0042 at 2026-11-02T09:00:00+07:00');
        $processedBooking = $processor($recordBooking);
        $this->assertSame('booked APT-20261102-0042 at 2026-11-02T09:00:00+07:00', $processedBooking->message);

        // 3. Context keys redaction: email dropped (nested), Authorization dropped, appointmentId preserved
        $context = [
            'user' => [
                'email' => 'a@b.c',
                'name' => 'John',
            ],
            'appointmentId' => '01935512-7712-7abc-9def-0123456789ab',
            'Authorization' => 'x',
            'token' => 'secret-token',
            'cookie' => 'laravel_session=xyz',
        ];

        $recordContext = $this->makeRecord('context test', $context);
        $processedContext = $processor($recordContext);

        $this->assertArrayNotHasKey('email', $processedContext->context['user']);
        $this->assertSame('John', $processedContext->context['user']['name']);
        $this->assertArrayNotHasKey('Authorization', $processedContext->context);
        $this->assertArrayNotHasKey('token', $processedContext->context);
        $this->assertArrayNotHasKey('cookie', $processedContext->context);

        $this->assertArrayHasKey('appointmentId', $processedContext->context);
        $this->assertSame('01935512-7712-7abc-9def-0123456789ab', $processedContext->context['appointmentId']);
    }

    public function test_TC_08_14_standalone_string_and_context_redaction(): void
    {
        $this->assertSame(
            'User [email] requested callback at [phone]',
            RedactPersonalData::redact('User user@example.com requested callback at 0903 123 218'),
        );

        $redacted = RedactPersonalData::redactContext([
            'correlationId' => '01935512-7712-7abc-9def-0123456789ab',
            'reference' => 'APT-20261102-0042',
            'route' => 'POST /api/v1/appointments',
            'occurredAt' => '2026-11-02T09:00:00+07:00',
            'phone_number' => '0903123218',
            'password' => 'secret123',
            'notes' => 'Customer email is test@domain.org',
        ]);

        $this->assertSame('01935512-7712-7abc-9def-0123456789ab', $redacted['correlationId']);
        $this->assertSame('APT-20261102-0042', $redacted['reference']);
        $this->assertSame('POST /api/v1/appointments', $redacted['route']);
        $this->assertSame('2026-11-02T09:00:00+07:00', $redacted['occurredAt']);
        $this->assertArrayNotHasKey('phone_number', $redacted);
        $this->assertArrayNotHasKey('password', $redacted);
        $this->assertSame('Customer email is [email]', $redacted['notes']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function makeRecord(string $message, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable('2026-11-02T09:00:00+07:00'),
            channel: 'testing',
            level: Level::Info,
            message: $message,
            context: $context,
            extra: [],
        );
    }
}
