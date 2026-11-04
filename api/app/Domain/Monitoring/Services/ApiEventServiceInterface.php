<?php

namespace App\Domain\Monitoring\Services;

use App\Models\ApiEvent;

interface ApiEventServiceInterface
{
    /**
     * Record an API event, ensuring personal data in detail is redacted (INV-08.7).
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): ApiEvent;
}
