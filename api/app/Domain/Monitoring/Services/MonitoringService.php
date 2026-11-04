<?php

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use Carbon\CarbonImmutable;

class MonitoringService implements MonitoringServiceInterface
{
    public function __construct(
        private readonly ApiEventRepositoryInterface $apiEventRepository,
    ) {}

    public function getSummary(User $admin, string $range): array
    {
        $now = CarbonImmutable::now();
        $from = match ($range) {
            '1h' => $now->subHour(),
            '7d' => $now->subDays(7),
            default => $now->subHours(24),
        };
        $to = $now;

        $dealership = $admin->dealership;
        $tz = $dealership !== null ? $dealership->timezone : (string) config('app.timezone', 'UTC');

        $dealershipId = $admin->dealership_id;

        $bookingMetrics = $this->apiEventRepository->getBookingMetrics($dealershipId, $from, $to);
        $dbHealth = $this->apiEventRepository->getDatabaseHealth();
        $outboxHealth = $this->apiEventRepository->getOutboxHealth();
        $recentErrors = $this->apiEventRepository->getRecentErrors($dealershipId, $from, $to, 20);

        return [
            'range' => $range,
            'from' => $from->setTimezone($tz)->toIso8601String(),
            'to' => $to->setTimezone($tz)->toIso8601String(),
            'health' => [
                'app' => [
                    'status' => 'UP',
                    'bookingP95Ms' => $bookingMetrics['p95Ms'],
                ],
                'database' => $dbHealth,
                'outbox' => $outboxHealth,
            ],
            'bookings' => [
                'total' => $bookingMetrics['total'],
                'confirmed' => $bookingMetrics['confirmed'],
                'noAvailability' => $bookingMetrics['noAvailability'],
                'validationError' => $bookingMetrics['validationError'],
                'error' => $bookingMetrics['error'],
                'p95Ms' => $bookingMetrics['p95Ms'],
                'allocationRetries' => $bookingMetrics['allocationRetries'],
                'idempotentReplays' => $bookingMetrics['idempotentReplays'],
                'appointmentModified' => $bookingMetrics['appointmentModified'],
            ],
            'recentErrors' => $recentErrors,
        ];
    }

    public function listErrors(User $admin, ?string $correlationId, ?string $cursor, int $limit = 20): CursorPage
    {
        $dealershipId = $admin->dealership_id;

        return $this->apiEventRepository->listErrors(
            dealershipId: $dealershipId,
            correlationId: $correlationId,
            cursor: $cursor,
            limit: $limit,
        );
    }
}
