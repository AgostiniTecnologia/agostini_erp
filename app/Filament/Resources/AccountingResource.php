<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingResource\Pages;
use App\Filament\Resources\AccountingResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Navigation\NavigationItem;

class AccountingResource extends Resource
{
    protected static ?string $model = Null;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Contábil';
    protected static ?string $modelLabel = 'NF-e';
    protected static ?string $pluralModelLabel = 'NF-e';
    protected static ?int $navigationSort = 82;
    
    public static function getNavigationItems(): array
    {
        return [
            // Grupo CONTÁBIL
            \Filament\Navigation\NavigationItem::make('NF-e')
                ->url('https://www.nfse.gov.br/EmissorNacional/Login?ReturnUrl=%2fEmissorNacional', shouldOpenInNewTab: true)
                ->icon('heroicon-o-document-text')
                ->group('Contábil')
                ->sort(99),

            \Filament\Navigation\NavigationItem::make('Boleto')
                ->url('https://site-do-boleto.com', shouldOpenInNewTab: true)
                ->icon('heroicon-o-banknotes')
                ->group('Contábil')
                ->sort(99),

            \Filament\Navigation\NavigationItem::make('Extrato')
                ->url('https://site-do-extrato.com', shouldOpenInNewTab: true)
                ->icon('heroicon-o-clipboard-document-list')
                ->group('Contábil')
                ->sort(99),
        ];
    }
}
