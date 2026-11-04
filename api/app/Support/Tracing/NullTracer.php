<?php

namespace App\Support\Tracing;

final class NullTracer implements Tracer, TracerInterface
{
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
        return $fn();
    }
}
