<?php

namespace App\Domain\MasterData;

use InvalidArgumentException;

final readonly class Level
{
    public const int MIN = 1;

    public const int MAX = 7;

    public function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidArgumentException('Level must be between '.self::MIN.' and '.self::MAX.", got {$value}");
        }
    }

    public static function from(int $value): self
    {
        return new self($value);
    }

    public function qualifiesFor(self $required): bool
    {
        return $this->value >= $required->value;
    }

    public function label(): string
    {
        return "Level {$this->value}";
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
