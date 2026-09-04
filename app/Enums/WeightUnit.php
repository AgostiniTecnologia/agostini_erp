<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WeightUnit: string implements HasLabel
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Tonne = 't';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Gram => 'Grama (g)',
            self::Kilogram => 'Quilograma (kg)',
            self::Tonne => 'Tonelada (t)',
        };
    }
}
