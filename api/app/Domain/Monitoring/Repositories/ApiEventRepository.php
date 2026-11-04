<?php

namespace App\Domain\Monitoring\Repositories;

use App\Models\ApiEvent;
use Carbon\CarbonImmutable;

class ApiEventRepository implements ApiEventRepositoryInterface
{
    public function record(array $data): ApiEvent
    {
        if (! isset($data['occurred_at'])) {
            $data['occurred_at'] = CarbonImmutable::now();
        }

        if (! isset($data['detail']) || ! is_array($data['detail'])) {
            $data['detail'] = [];
        }

        if (isset($data['method']) && is_string($data['method'])) {
            $data['method'] = substr($data['method'], 0, 8);
        }

        if (isset($data['route']) && is_string($data['route'])) {
            $data['route'] = substr($data['route'], 0, 160);
        }

        if (isset($data['correlation_id']) && is_string($data['correlation_id'])) {
            $data['correlation_id'] = substr($data['correlation_id'], 0, 128);
        }

        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = substr($data['code'], 0, 48);
        }

        if (isset($data['outcome']) && is_string($data['outcome'])) {
            $data['outcome'] = substr($data['outcome'], 0, 24);
        }

        return ApiEvent::query()->create($data);
    }
}
