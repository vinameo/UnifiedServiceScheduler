<?php

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use App\Models\ApiEvent;
use App\Support\Logging\RedactPersonalData;

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
}
