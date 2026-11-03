<?php

namespace App\Domain\Allocation;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class TimeRange
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(
        CarbonImmutable|DateTimeInterface|string $start,
        CarbonImmutable|DateTimeInterface|string $end,
    ) {
        $startUtc = self::toUtcImmutable($start);
        $endUtc = self::toUtcImmutable($end);

        if (! $startUtc->lessThan($endUtc)) {
            throw new InvalidArgumentException(sprintf(
                'TimeRange start (%s) must be before end (%s).',
                $startUtc->toIso8601String(),
                $endUtc->toIso8601String(),
            ));
        }

        $this->start = $startUtc;
        $this->end = $endUtc;
    }

    public static function of(
        CarbonImmutable|DateTimeInterface|string $start,
        CarbonImmutable|DateTimeInterface|string $end,
    ): self {
        return new self($start, $end);
    }

    /**
     * Half-open overlap: [s1, e1) overlaps [s2, e2) iff s1 < e2 and s2 < e1 (BR-03.1).
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    /**
     * Checks if this range contains another range or a specific point in time [start, end).
     */
    public function contains(self|CarbonImmutable|DateTimeInterface|string $pointOrRange): bool
    {
        if ($pointOrRange instanceof self) {
            return $this->start->lessThanOrEqualTo($pointOrRange->start)
                && $this->end->greaterThanOrEqualTo($pointOrRange->end);
        }

        $pointUtc = self::toUtcImmutable($pointOrRange);

        return $this->start->lessThanOrEqualTo($pointUtc) && $pointUtc->lessThan($this->end);
    }

    public function minutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    private static function toUtcImmutable(CarbonImmutable|DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->setTimezone('UTC');
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone('UTC');
        }

        return CarbonImmutable::parse($value)->setTimezone('UTC');
    }
}

