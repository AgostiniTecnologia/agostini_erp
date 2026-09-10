<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SalesVisitsDefaultView: string implements HasLabel
{
    case Map = 'map';
    case List = 'list';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Map => 'Mapa',
            self::List => 'Lista',
        };
    }
}
