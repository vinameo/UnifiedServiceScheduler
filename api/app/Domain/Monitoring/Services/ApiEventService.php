<?php

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use App\Models\ApiEvent;
use App\Support\Logging\RedactPersonalData;
use Carbon\CarbonImmutable;

class ApiEventService implements ApiEventServiceInterface
{
    public function __construct(
        private readonly ApiEventRepositoryInterface $repository,
    ) {}

    public function record(array $data): ApiEvent
    {
        if (isset($data['detail']) && is_array($data['detail'])) {
            $data['detail'] = RedactPersonalData::redactArray($data['detail']);
        } else {
            $data['detail'] = [];
        }

        return $this->repository->record($data);
    }

    public function pruneOlderThan(CarbonImmutable $cutoff): int
    {
        return $this->repository->pruneOlderThan($cutoff);
    }

    public function prune(int|CarbonImmutable $cutoffOrDays = 7): int
    {
        return $this->repository->prune($cutoffOrDays);
    }
}
