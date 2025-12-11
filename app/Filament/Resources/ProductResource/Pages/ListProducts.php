<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Livewire\Traits\LivewireOfflineData;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use LivewireOfflineData;

    protected static string $resource = ProductResource::class;
    
    public string $offlineStoreName = 'products';

    public function mount(): void
    {
        parent::mount();
        $this->loadOfflineData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Produto')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getHeading(): string
    {
        $heading = 'Produtos';
        
        if ($this->isOffline) {
            $heading .= ' (Modo Offline)';
        }
        
        return $heading;
    }

    public function getSubheading(): ?string
    {
        if ($this->isOffline) {
            return 'Você está offline. Alterações serão sincronizadas automaticamente quando reconectar.';
        }
        
        return null;
    }
}