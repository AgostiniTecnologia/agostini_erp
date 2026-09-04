<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LengthUnit: string implements HasLabel
{
    case Millimeter = 'mm';
    case Centimeter = 'cm';
    case Meter = 'm';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Millimeter => 'Milímetro (mm)',
            self::Centimeter => 'Centímetro (cm)',
            self::Meter => 'Metro (m)',
        };
    }
}
