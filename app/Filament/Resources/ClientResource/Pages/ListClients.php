<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Livewire\Traits\LivewireOfflineData;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    use LivewireOfflineData;

    protected static string $resource = ClientResource::class;
    
    /**
     * Define qual store do IndexedDB será usado
     * IMPORTANTE: Declarar aqui, não no trait
     */
    public string $offlineStoreName = 'clients';

    /**
     * Inicialização do componente
     */
    public function mount(): void
    {
        parent::mount();
        
        // Carregar dados offline disponíveis
        $this->loadOfflineData();
    }

    /**
     * Ações do cabeçalho
     * NOTA: Sem botões de sincronização - tudo é automático!
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Cliente')
                ->icon('heroicon-o-plus')
                // Interceptar criação para salvar offline se necessário
                ->after(function ($record) {
                    // Se foi criado com sucesso online, garantir que está no cache offline
                    if ($record && navigator.onLine) {
                        $this->dispatch('saveOfflineData', 
                            storeName: 'clients',
                            item: $record->toArray()
                        );
                    }
                }),
        ];
    }

    /**
     * Personalizar cabeçalho da página
     */
    public function getHeading(): string
    {
        $heading = 'Clientes';
        
        if ($this->isOffline) {
            $heading .= ' (Modo Offline)';
        }
        
        return $heading;
    }

    /**
     * Exibir mensagem informativa quando offline
     */
    public function getSubheading(): ?string
    {
        if ($this->isOffline) {
            return '⚠️ Você está offline. Alterações serão sincronizadas automaticamente quando reconectar.';
        }
        
        return null;
    }
}