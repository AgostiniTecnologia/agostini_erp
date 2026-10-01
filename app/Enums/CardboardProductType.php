<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CardboardProductType: string implements HasLabel
{
    case Standard = 'standard';
    case Box = 'box';
    case Sheet = 'sheet';
    case Corner = 'corner';
    case Briefcase = 'briefcase';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Standard => 'Padrão',
            self::Box => 'Caixa envoltório',
            self::Sheet => 'Chapa',
            self::Corner => 'Cantoneira',
            self::Briefcase => 'Caixa Maleta',
        };
    }
}
