<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CardboardProductType: string implements HasLabel
{
    case Standard = 'standard';
    case Box = 'box';
    case Sheet = 'sheet';
    case Corner = 'corner';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Standard => 'Padrão',
            self::Box => 'Caixa de papelão',
            self::Sheet => 'Chapa',
            self::Corner => 'Cantoneira',
        };
    }
}
