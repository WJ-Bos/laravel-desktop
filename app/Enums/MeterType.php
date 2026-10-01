<?php

namespace App\Enums;

enum MeterType: string
{
    case SinglePhase = 'single_phase';
    case ThreePhase = 'three_phase';
    case Ct = 'ct';
    case CtVt = 'ct_vt';
    case DinRail = 'din_rail';

    public function label(): string
    {
        return match ($this) {
            self::SinglePhase => 'Single-phase',
            self::ThreePhase => 'Three-phase',
            self::Ct => 'CT',
            self::CtVt => 'CT/VT',
            self::DinRail => 'DIN-rail',
        };
    }

    public function phases(): int
    {
        return $this === self::SinglePhase ? 1 : 3;
    }
}
