<?php

namespace App\Support\Problem;

use RuntimeException;

/**
 * Base for business errors with a stable code (see "Shared rules" in the requirements).
 * Feature exceptions extend it, for example AppointmentModified (412 APPOINTMENT_MODIFIED).
 */
class ProblemException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly int $status,
        public readonly string $problemCode,
        string $title,
        public readonly ?string $detail = null,
        public readonly array $extra = [],
    ) {
        parent::__construct($title);
    }

    public function toProblem(): Problem
    {
        return new Problem($this->status, $this->problemCode, $this->getMessage(), $this->detail, $this->extra);
    }
}
