<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CardboardSheetType: string implements HasLabel
{
    case Simple = 'simple';
    case Double = 'double';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Simple => 'Chapa simples',
            self::Double => 'Chapa dupla',
        };
    }
}
