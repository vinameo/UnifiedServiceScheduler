<?php

namespace App\Support\Tracing;

interface Tracer
{
    /**
     * Executes the given callback within a named span.
     *
     * @template T
     *
     * @param  non-empty-string  $name
     * @param  callable(): T  $fn
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    public function span(string $name, callable $fn, array $attributes = []): mixed;
}
