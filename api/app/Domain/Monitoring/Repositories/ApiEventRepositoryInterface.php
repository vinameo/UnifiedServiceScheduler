<?php

namespace App\Domain\Monitoring\Repositories;

use App\Models\ApiEvent;

interface ApiEventRepositoryInterface
{
    /**
     * Persist an API event record.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): ApiEvent;
}
