<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Livewire\Traits\LivewireOfflineData;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateProduct extends CreateRecord
{
    use LivewireOfflineData;

    protected static string $resource = ProductResource::class;
    
    public string $offlineStoreName = 'products';
    public bool $isOffline = false;

    public function mount(): void
    {
        parent::mount();
        $this->loadOfflineData();
    }

    /**
     * Hook ANTES de criar o registro
     */
    protected function beforeCreate(): void
    {
        if (!$this->checkOnlineStatus()) {
            $this->handleOfflineCreate();
        }
    }

    /**
     * Hook APÓS criar o registro
     */
    protected function afterCreate(): void
    {
        if ($this->checkOnlineStatus() && $this->record) {
            $this->dispatch('saveOfflineData',
                storeName: 'products',
                item: $this->record->toArray()
            );
            
            Log::info('Produto criado e cacheado offline', [
                'product_id' => $this->record->id
            ]);
        }
    }

    /**
     * Manipula criação offline
     */
    protected function handleOfflineCreate(): void
    {
        $data = $this->form->getState();
        
        // Adicionar company_id
        $data['company_id'] = auth()->user()->company_id;
        
        // Salvar offline
        $tempId = $this->saveOfflineRecord($data, 'create');
        
        Log::info('Produto salvo offline', [
            'temp_id' => $tempId,
            'data' => $data
        ]);
        
        Notification::make()
            ->title('Produto salvo offline')
            ->body('Será sincronizado automaticamente quando reconectar.')
            ->success()
            ->send();
        
        // Redirecionar para lista
        $this->redirect(static::getResource()::getUrl('index'));
        
        // Cancelar criação normal
        $this->halt();
    }

    /**
     * Verifica se está online
     */
    protected function checkOnlineStatus(): bool
    {
        try {
            $response = Http::timeout(2)->head(config('app.url'));
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}