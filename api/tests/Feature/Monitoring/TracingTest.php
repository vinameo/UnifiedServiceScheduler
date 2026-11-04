<?php

namespace Tests\Feature\Monitoring;

use App\Support\Tracing\NullTracer;
use App\Support\Tracing\Tracer;
use ArrayObject;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class TracingTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        putenv('OTEL_SDK_DISABLED=true');
        $_ENV['OTEL_SDK_DISABLED'] = 'true';
        $_SERVER['OTEL_SDK_DISABLED'] = 'true';
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-08-11 (AC-08.10; Integration):
     * given InMemoryExporter, OTEL_SDK_DISABLED=false;
     * when Book;
     * then Spans http.request, booking.validate, allocation.find_candidates, booking.write;
     * one traceId; the last 3 are children of http.request.
     */
    public function test_AC_08_10_booking_spans(): void
    {
        putenv('OTEL_SDK_DISABLED=false');
        $_ENV['OTEL_SDK_DISABLED'] = 'false';
        $_SERVER['OTEL_SDK_DISABLED'] = 'false';
        config(['tracing.disabled' => false]);

        $storage = new ArrayObject;
        $exporter = new InMemoryExporter($storage);
        $this->app->instance(SpanExporterInterface::class, $exporter);
        $this->app->forgetInstance(Tracer::class);

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Corolla', 'plate_key' => '51H99999', 'licence_plate' => '51H-999.99']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
            'reminderOptIn' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(201);
        $this->assertNoOverlappingAllocations();

        /** @var list<ImmutableSpan> $spans */
        $spans = (array) $exporter->getSpans();
        $this->assertCount(4, $spans, 'Expected exactly 4 spans for booking flow');

        $spanNames = array_map(fn (ImmutableSpan $s) => $s->getName(), $spans);
        $expectedNames = ['booking.validate', 'allocation.find_candidates', 'booking.write', 'http.request'];
        sort($spanNames);
        sort($expectedNames);
        $this->assertSame($expectedNames, $spanNames);

        // All 4 spans share one traceId
        $traceIds = array_unique(array_map(fn (ImmutableSpan $s) => $s->getTraceId(), $spans));
        $this->assertCount(1, $traceIds, 'All spans must share exactly one traceId');
        $traceId = reset($traceIds);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $traceId);

        // Find root span http.request
        $rootSpan = null;
        $childSpans = [];
        foreach ($spans as $span) {
            if ($span->getName() === 'http.request') {
                $rootSpan = $span;
            } else {
                $childSpans[] = $span;
            }
        }

        $this->assertNotNull($rootSpan, 'Root span http.request not found');
        $this->assertSame('0000000000000000', $rootSpan->getParentSpanId(), 'http.request should be root span');

        $this->assertCount(3, $childSpans);
        foreach ($childSpans as $child) {
            $this->assertSame(
                $rootSpan->getSpanId(),
                $child->getParentSpanId(),
                sprintf('Span %s should have http.request (%s) as parent', $child->getName(), $rootSpan->getSpanId())
            );
        }
    }

    public function test_AC_08_10_noop_when_disabled(): void
    {
        putenv('OTEL_SDK_DISABLED=true');
        $_ENV['OTEL_SDK_DISABLED'] = 'true';
        $_SERVER['OTEL_SDK_DISABLED'] = 'true';
        config(['tracing.disabled' => true]);

        $this->app->forgetInstance(Tracer::class);
        $tracer = $this->app->make(Tracer::class);
        $this->assertInstanceOf(NullTracer::class, $tracer);

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Camry', 'plate_key' => '51H88888', 'licence_plate' => '51H-888.88']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T10:00:00+07:00',
            'reminderOptIn' => false,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(201);
        $this->assertNoOverlappingAllocations();
    }
}
