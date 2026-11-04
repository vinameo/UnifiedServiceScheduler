<?php

namespace App\Support\Tracing;

use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface as OpenTelemetryTracerInterface;
use Throwable;

final class OtelTracer implements Tracer, TracerInterface
{
    public function __construct(
        private readonly OpenTelemetryTracerInterface $tracer,
    ) {}

    /**
     * @template T
     *
     * @param  non-empty-string  $name
     * @param  callable(): T  $fn
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    public function span(string $name, callable $fn, array $attributes = []): mixed
    {
        $builder = $this->tracer->spanBuilder($name);
        foreach ($attributes as $key => $value) {
            if (is_scalar($value)) {
                $builder->setAttribute($key, $value);
            }
        }

        $span = $builder->startSpan();
        $scope = $span->activate();

        try {
            return $fn();
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
