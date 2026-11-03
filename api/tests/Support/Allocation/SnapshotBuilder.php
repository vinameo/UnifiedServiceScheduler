<?php

namespace Tests\Support\Allocation;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\BayState;
use App\Domain\Allocation\BusyInterval;
use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class SnapshotBuilder
{
    public const string DEFAULT_DEALERSHIP_ID = '00000000-0000-0000-0000-000000000001';

    /**
     * @var array<string, TechnicianState>
     */
    private array $technicians = [];

    /**
     * @var array<string, BayState>
     */
    private array $bays = [];

    public function __construct(
        public readonly string $localDate,
        public readonly string $timezone = 'Asia/Ho_Chi_Minh',
        public readonly string $dealershipId = self::DEFAULT_DEALERSHIP_ID,
    ) {}

    public static function day(
        string $localDate,
        string $timezone = 'Asia/Ho_Chi_Minh',
        string $dealershipId = self::DEFAULT_DEALERSHIP_ID,
    ): self {
        return new self($localDate, $timezone, $dealershipId);
    }

    public function technician(
        string $name,
        int|Level $level = 1,
        bool $active = true,
        ?string $id = null,
    ): self {
        $levelObj = $level instanceof Level ? $level : Level::from($level);
        $techId = $id ?? Uuid::uuid5(Uuid::NAMESPACE_DNS, "technician:{$name}")->toString();

        $this->technicians[$name] = new TechnicianState(
            id: $techId,
            name: $name,
            level: $levelObj,
            active: $active,
            busy: [],
        );

        return $this;
    }

    public function bay(
        string $code,
        string|BayType $bayType = BayType::STANDARD,
        bool $active = true,
        ?string $id = null,
    ): self {
        $type = is_string($bayType) ? BayType::from($bayType) : $bayType;
        $bayId = $id ?? Uuid::uuid5(Uuid::NAMESPACE_DNS, "bay:{$code}")->toString();

        $this->bays[$code] = new BayState(
            id: $bayId,
            code: $code,
            bayType: $type,
            active: $active,
            busy: [],
        );

        return $this;
    }

    public function busy(
        string $resourceNameOrCode,
        string|TimeRange $timeOrRange,
        ?string $appointment = null,
    ): self {
        $range = is_string($timeOrRange)
            ? $this->parseLocalTimeRange($timeOrRange)
            : $timeOrRange;

        $interval = new BusyInterval($range, $appointment);

        if (isset($this->technicians[$resourceNameOrCode])) {
            $tech = $this->technicians[$resourceNameOrCode];
            $busy = $tech->busy;
            $busy[] = $interval;
            $this->technicians[$resourceNameOrCode] = new TechnicianState(
                id: $tech->id,
                name: $tech->name,
                level: $tech->level,
                active: $tech->active,
                busy: $busy,
            );

            return $this;
        }

        foreach ($this->technicians as $key => $tech) {
            if ($tech->id === $resourceNameOrCode) {
                $busy = $tech->busy;
                $busy[] = $interval;
                $this->technicians[$key] = new TechnicianState(
                    id: $tech->id,
                    name: $tech->name,
                    level: $tech->level,
                    active: $tech->active,
                    busy: $busy,
                );

                return $this;
            }
        }

        if (isset($this->bays[$resourceNameOrCode])) {
            $bay = $this->bays[$resourceNameOrCode];
            $busy = $bay->busy;
            $busy[] = $interval;
            $this->bays[$resourceNameOrCode] = new BayState(
                id: $bay->id,
                code: $bay->code,
                bayType: $bay->bayType,
                active: $bay->active,
                busy: $busy,
            );

            return $this;
        }

        foreach ($this->bays as $key => $bay) {
            if ($bay->id === $resourceNameOrCode) {
                $busy = $bay->busy;
                $busy[] = $interval;
                $this->bays[$key] = new BayState(
                    id: $bay->id,
                    code: $bay->code,
                    bayType: $bay->bayType,
                    active: $bay->active,
                    busy: $busy,
                );

                return $this;
            }
        }

        throw new InvalidArgumentException("Resource '{$resourceNameOrCode}' not found in snapshot builder.");
    }

    public function build(): ResourceSnapshot
    {
        return new ResourceSnapshot(
            dealershipId: $this->dealershipId,
            timezone: $this->timezone,
            localDate: $this->localDate,
            technicians: array_values($this->technicians),
            bays: array_values($this->bays),
        );
    }

    public static function request(
        string|Level $serviceTypeOrLevel,
        string|TimeRange $timeOrRange,
        string|BayType|null $bayType = null,
        string $date = '2026-11-02',
        string $timezone = 'Asia/Ho_Chi_Minh',
        string $dealershipId = self::DEFAULT_DEALERSHIP_ID,
        ?string $preferTechnicianId = null,
        ?string $preferBayId = null,
        ?string $excludeAppointmentId = null,
        int $maxPairs = 5,
    ): AllocationRequest {
        [$level, $resolvedBayType] = self::resolveServiceType($serviceTypeOrLevel, $bayType);

        $range = is_string($timeOrRange)
            ? self::parseRangeOnDate($timeOrRange, $date, $timezone)
            : $timeOrRange;

        return new AllocationRequest(
            dealershipId: $dealershipId,
            requiredLevel: $level,
            requiredBayType: $resolvedBayType,
            range: $range,
            preferTechnicianId: $preferTechnicianId,
            preferBayId: $preferBayId,
            excludeAppointmentId: $excludeAppointmentId,
            maxPairs: $maxPairs,
        );
    }

    private function parseLocalTimeRange(string $timeRange): TimeRange
    {
        return self::parseRangeOnDate($timeRange, $this->localDate, $this->timezone);
    }

    private static function parseRangeOnDate(string $timeRange, string $date, string $timezone): TimeRange
    {
        $parts = explode('-', $timeRange);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Expected time range format 'HH:MM-HH:MM', got '{$timeRange}'");
        }

        [$startStr, $endStr] = [trim($parts[0]), trim($parts[1])];

        $start = CarbonImmutable::parse("{$date} {$startStr}:00", $timezone)->setTimezone('UTC');
        $end = CarbonImmutable::parse("{$date} {$endStr}:00", $timezone)->setTimezone('UTC');

        return TimeRange::of($start, $end);
    }

    /**
     * @return array{Level, BayType}
     */
    private static function resolveServiceType(string|Level $serviceTypeOrLevel, string|BayType|null $bayType): array
    {
        if ($serviceTypeOrLevel instanceof Level) {
            $resolvedBayType = $bayType instanceof BayType
                ? $bayType
                : ($bayType !== null ? BayType::from($bayType) : BayType::STANDARD);

            return [$serviceTypeOrLevel, $resolvedBayType];
        }

        return match ($serviceTypeOrLevel) {
            'OIL', 'OIL_CHANGE' => [
                Level::from(1),
                $bayType ? (is_string($bayType) ? BayType::from($bayType) : $bayType) : BayType::QUICK,
            ],
            'MAINT_20K' => [
                Level::from(3),
                $bayType ? (is_string($bayType) ? BayType::from($bayType) : $bayType) : BayType::STANDARD,
            ],
            'EV_BATTERY' => [
                Level::from(5),
                $bayType ? (is_string($bayType) ? BayType::from($bayType) : $bayType) : BayType::EV,
            ],
            default => [
                Level::from(1),
                $bayType ? (is_string($bayType) ? BayType::from($bayType) : $bayType) : BayType::STANDARD,
            ],
        };
    }
}
