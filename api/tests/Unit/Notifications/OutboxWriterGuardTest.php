<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Services\OutboxWriter;
use Carbon\CarbonImmutable;
use LogicException;
use Tests\TestCase;

class OutboxWriterGuardTest extends TestCase
{
    public function test_INV_04_12_append_outside_transaction_throws(): void
    {
        $writer = new OutboxWriter;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('OutboxWriter::append must be called inside an active database transaction.');

        $writer->append(
            dealershipId: '00000000-0000-0000-0000-000000000001',
            appointmentId: '00000000-0000-0000-0000-000000000002',
            eventType: OutboxEventType::AppointmentConfirmed,
            payload: ['foo' => 'bar'],
            now: CarbonImmutable::now(),
        );
    }
}
