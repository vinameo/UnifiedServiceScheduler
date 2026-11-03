<?php

namespace Tests\Unit\Domain\MasterData;

use App\Domain\MasterData\BayType;
use PHPUnit\Framework\TestCase;

class BayTypeTest extends TestCase
{
    public function test_bay_type_cases_and_labels(): void
    {
        $this->assertSame('QUICK', BayType::QUICK->value);
        $this->assertSame('STANDARD', BayType::STANDARD->value);
        $this->assertSame('EV', BayType::EV->value);

        $this->assertSame('Quick service', BayType::QUICK->label());
        $this->assertSame('Standard service', BayType::STANDARD->label());
        $this->assertSame('EV', BayType::EV->label());
    }

    public function test_bay_type_from_string(): void
    {
        $this->assertSame(BayType::QUICK, BayType::from('QUICK'));
        $this->assertSame(BayType::STANDARD, BayType::from('STANDARD'));
        $this->assertSame(BayType::EV, BayType::from('EV'));
    }
}
