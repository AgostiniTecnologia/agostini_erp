<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OperationalProfile: string implements HasLabel
{
    case Standard = 'standard';
    case Furniture = 'furniture';
    case CardboardPackaging = 'cardboard_packaging';
    case Dairy = 'dairy';
    case Clothing = 'clothing';
    case Metallurgical = 'metallurgical';
    case Printing = 'printing';
    case FoodIndustry = 'food_industry';
    case BeverageIndustry = 'beverage_industry';
    case PlasticsIndustry = 'plastics_industry';
    case WholesaleDistributor = 'wholesale_distributor';
    case Services = 'services';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Standard => 'Padrão',
            self::Furniture => 'Indústria moveleira',
            self::CardboardPackaging => 'Cartonagem e embalagens de papelão',
            self::Dairy => 'Laticínios',
            self::Clothing => 'Confecção',
            self::Metallurgical => 'Metalúrgica',
            self::Printing => 'Gráfica',
            self::FoodIndustry => 'Indústria alimentícia',
            self::BeverageIndustry => 'Indústria de bebidas',
            self::PlasticsIndustry => 'Indústria de plásticos',
            self::WholesaleDistributor => 'Distribuidora / Atacadista',
            self::Services => 'Prestadora de serviços',
        };
    }
}
