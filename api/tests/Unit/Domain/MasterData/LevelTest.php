<?php

namespace Tests\Unit\Domain\MasterData;

use App\Domain\MasterData\Level;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LevelTest extends TestCase
{
    public function test_level_accepts_values_between_1_and_7(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $level = new Level($i);
            $this->assertSame($i, $level->value);
            $this->assertSame("Level {$i}", $level->label());
        }
    }

    public function test_level_refuses_zero_and_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Level(0);
    }

    public function test_level_refuses_values_above_7(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Level(8);
    }

    public function test_qualifies_for_requires_greater_or_equal_level(): void
    {
        $techLevel4 = new Level(4);
        $serviceLevel3 = new Level(3);
        $serviceLevel4 = new Level(4);
        $serviceLevel5 = new Level(5);

        $this->assertTrue($techLevel4->qualifiesFor($serviceLevel3));
        $this->assertTrue($techLevel4->qualifiesFor($serviceLevel4));
        $this->assertFalse($techLevel4->qualifiesFor($serviceLevel5));
    }
}
