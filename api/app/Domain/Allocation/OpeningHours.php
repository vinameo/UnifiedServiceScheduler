<?php

namespace App\Domain\Allocation;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final readonly class OpeningHours
{
    /**
     * @param  array<int, array{opens_at: string, closes_at: string}>  $hours  By weekday (1=Monday .. 7=Sunday)
     * @param  string  $timezone  Dealership IANA timezone name (e.g. 'Asia/Ho_Chi_Minh', 'Europe/Berlin')
     */
    public function __construct(
        public array $hours,
        public string $timezone,
    ) {
        try {
            new DateTimeZone($timezone);
        } catch (Throwable $e) {
            throw new InvalidArgumentException("Invalid timezone '{$timezone}'.", 0, $e);
        }
    }

    /**
     * @param  array<array-key, array{opens_at?: string, closes_at?: string, opensAt?: string, closesAt?: string}|object|null>  $map
     */
    public static function fromWeekdayMap(array $map, string $timezone): self
    {
        $hours = [];

        foreach ($map as $dayKey => $config) {
            $day = (int) $dayKey;
            if ($day < 1 || $day > 7) {
                throw new InvalidArgumentException("Weekday must be between 1 (Monday) and 7 (Sunday), got {$day}.");
            }

            if ($config === null) {
                continue;
            }

            if (is_array($config)) {
                $opensAt = $config['opens_at'] ?? $config['opensAt'] ?? null;
                $closesAt = $config['closes_at'] ?? $config['closesAt'] ?? null;
            } elseif (is_object($config)) {
                $opensAt = $config->opens_at ?? $config->opensAt ?? null;
                $closesAt = $config->closes_at ?? $config->closesAt ?? null;
            } else {
                throw new InvalidArgumentException("Opening hours for weekday {$day} must be an array or object.");
            }

            if (! is_string($opensAt) || ! is_string($closesAt)) {
                throw new InvalidArgumentException("Opening hours for weekday {$day} must specify string opens_at and closes_at.");
            }

            $opensAt = trim($opensAt);
            $closesAt = trim($closesAt);

            if (strlen($opensAt) === 5) {
                $opensAt .= ':00';
            }
            if (strlen($closesAt) === 5) {
                $closesAt .= ':00';
            }

            if ($closesAt <= $opensAt) {
                throw new InvalidArgumentException(
                    "Opening hours for weekday {$day} are invalid: closes_at ({$closesAt}) must be after opens_at ({$opensAt})."
                );
            }

            $hours[$day] = [
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ];
        }

        return new self($hours, $timezone);
    }

    /**
     * Returns TimeRange in UTC for opening hours on that local date.
     * If closed or no hours for that weekday, returns null.
     * Handles DST transitions using the dealership timezone.
     */
    public function windowFor(string $localDate): ?TimeRange
    {
        try {
            $date = CarbonImmutable::parse("{$localDate} 00:00:00", $this->timezone);
        } catch (Throwable $e) {
            throw new InvalidArgumentException("Invalid local date: {$localDate}.", 0, $e);
        }

        $weekday = (int) $date->isoWeekday();

        if (! isset($this->hours[$weekday])) {
            return null;
        }

        $opensAt = $this->hours[$weekday]['opens_at'];
        $closesAt = $this->hours[$weekday]['closes_at'];

        $startUtc = CarbonImmutable::parse("{$localDate} {$opensAt}", $this->timezone)->setTimezone('UTC');
        $endUtc = CarbonImmutable::parse("{$localDate} {$closesAt}", $this->timezone)->setTimezone('UTC');

        return TimeRange::of($startUtc, $endUtc);
    }

    /**
     * Checks if the local day containing $range->start has an opening window and that
     * window contains the whole $range. Also checks that $range does not cross into
     * a different local day (BR-02.2).
     */
    public function fits(TimeRange $range): bool
    {
        $localStart = $range->start->setTimezone($this->timezone);
        $localStartDate = $localStart->toDateString();

        $window = $this->windowFor($localStartDate);
        if ($window === null) {
            return false;
        }

        if (! $window->contains($range)) {
            return false;
        }

        // Must not cross into a different local day (BR-02.2)
        $lastInstant = $range->end->subMicrosecond()->setTimezone($this->timezone);
        if ($lastInstant->toDateString() !== $localStartDate) {
            return false;
        }

        return true;
    }

    /**
     * Returns latest start time as CarbonImmutable in the dealership's timezone
     * (or null if closed or duration > open hours).
     */
    public function latestStartFor(string $localDate, int $durationMinutes): ?CarbonImmutable
    {
        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException("Duration minutes must be positive, got {$durationMinutes}.");
        }

        try {
            $date = CarbonImmutable::parse("{$localDate} 00:00:00", $this->timezone);
        } catch (Throwable $e) {
            throw new InvalidArgumentException("Invalid local date: {$localDate}.", 0, $e);
        }

        $weekday = (int) $date->isoWeekday();

        if (! isset($this->hours[$weekday])) {
            return null;
        }

        $opensAt = $this->hours[$weekday]['opens_at'];
        $closesAt = $this->hours[$weekday]['closes_at'];

        $openLocal = CarbonImmutable::parse("{$localDate} {$opensAt}", $this->timezone);
        $closeLocal = CarbonImmutable::parse("{$localDate} {$closesAt}", $this->timezone);

        $openMinutes = (int) $openLocal->diffInMinutes($closeLocal);
        if ($durationMinutes > $openMinutes) {
            return null;
        }

        return $closeLocal->subMinutes($durationMinutes);
    }
}
