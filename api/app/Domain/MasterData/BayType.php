<?php

namespace App\Domain\MasterData;

enum BayType: string
{
    case QUICK = 'QUICK';
    case STANDARD = 'STANDARD';
    case EV = 'EV';

    public function label(): string
    {
        return match ($this) {
            self::QUICK => 'Quick service',
            self::STANDARD => 'Standard service',
            self::EV => 'EV',
        };
    }
}
