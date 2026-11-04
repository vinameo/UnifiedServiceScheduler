<?php

namespace App\Http\Controllers;

use App\Domain\Monitoring\Services\MetricCounterServiceInterface;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(
        private readonly MetricCounterServiceInterface $metricsService,
    ) {}

    public function metrics(): Response
    {
        $payload = $this->metricsService->renderPrometheus();

        return response($payload, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }
}
