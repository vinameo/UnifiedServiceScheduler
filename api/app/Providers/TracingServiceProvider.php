<?php

namespace App\Providers;

use App\Support\Tracing\NullTracer;
use App\Support\Tracing\OtelTracer;
use App\Support\Tracing\Tracer;
use App\Support\Tracing\TracerInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Throwable;

final class TracingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/tracing.php', 'tracing');

        $this->app->singleton(Tracer::class, function ($app): Tracer {
            $disabled = (bool) config('tracing.disabled', true);

            if ($disabled) {
                return new NullTracer;
            }

            try {
                $exporter = $app->bound(SpanExporterInterface::class)
                    ? $app->make(SpanExporterInterface::class)
                    : (new SpanExporterFactory)->create();

                $processor = new SimpleSpanProcessor($exporter);
                $provider = new TracerProvider($processor);
                $serviceName = (string) config('tracing.service_name', 'unified-service-scheduler');

                return new OtelTracer($provider->getTracer($serviceName));
            } catch (Throwable $e) {
                Log::warning('Failed to initialize OpenTelemetry tracer: '.$e->getMessage());

                return new NullTracer;
            }
        });

        $this->app->alias(Tracer::class, TracerInterface::class);
    }
}
