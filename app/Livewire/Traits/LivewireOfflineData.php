<?php

namespace App\Livewire\Traits;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

/**
 * Trait LivewireOfflineData
 *
 * Fornece integração entre componentes Livewire/Filament e IndexedDB (offline storage).
 * 
 * USO:
 * 1. Use o trait no seu componente Livewire/Filament
 * 2. Defina $offlineStoreName (ex: 'clients', 'products')
 * 3. Chame $this->loadOfflineData() no mount() ou em um botão
 * 4. Use $this->offlineData para exibir os dados
 * 5. Use $this->saveOfflineRecord() para salvar alterações offline
 */
trait LivewireOfflineData
{
    /**
     * Nome do store no IndexedDB (deve ser sobrescrito no componente)
     * Ex: 'clients', 'products', 'orders'
     * 
     * IMPORTANTE: Declare esta propriedade no seu componente:
     * protected string $offlineStoreName = 'clients';
     */

    /**
     * Dados carregados do IndexedDB
     */
    public array $offlineData = [];

    /**
     * Indica se os dados offline estão carregados
     */
    public bool $offlineDataLoaded = false;

    /**
     * Indica se está em modo offline
     */
    public bool $isOffline = false;

    /**
     * Contadores para sincronização
     */
    public int $pendingSyncCount = 0;

    /**
     * Carrega dados do IndexedDB
     * Chame este método no mount() ou quando precisar carregar dados offline
     */
    public function loadOfflineData(): void
    {
        if (!property_exists($this, 'offlineStoreName') || empty($this->offlineStoreName)) {
            Log::warning('LivewireOfflineData: $offlineStoreName não definido no componente ' . static::class);
            return;
        }

        // Disparar evento para o frontend buscar dados
        $this->dispatch('fetchOfflineData', storeName: $this->offlineStoreName);
    }

    /**
     * Recebe os dados do frontend (chamado via evento JS)
     */
    #[On('setOfflineData')]
    public function setOfflineData($payload): void
    {
        $data = $payload['data'] ?? $payload ?? [];
        
        $this->offlineData = is_array($data) ? $data : [];
        $this->offlineDataLoaded = true;
        
        Log::info('Dados offline carregados', [
            'component' => static::class,
            'store' => $this->offlineStoreName,
            'count' => count($this->offlineData)
        ]);
    }

    /**
     * Manipula atualizações de dados offline vindas do JS
     */
    #[On('offline-data-updated')]
    public function handleOfflineDataUpdate($payload): void
    {
        // Extrair dados do payload (formato pode variar)
        if (is_array($payload) && count($payload) >= 3) {
            [$storeName, $action, $data] = $payload;
        } else {
            $storeName = $payload['storeName'] ?? null;
            $action = $payload['action'] ?? null;
            $data = $payload['payload'] ?? $payload['data'] ?? [];
        }

        // Ignorar se não à o store correto
        if ($storeName !== $this->offlineStoreName) {
            return;
        }

        // Atualizar array local
        $this->updateLocalOfflineData($action, $data);
    }

    /**
     * Atualiza o array local de dados offline
     */
    protected function updateLocalOfflineData(string $action, array $data): void
    {
        if (!is_array($this->offlineData)) {
            $this->offlineData = [];
        }

        switch ($action) {
            case 'create':
            case 'update':
                $id = $data['id'] ?? $data['uuid'] ?? null;
                if (!$id) break;

                // Procurar Ã­ndice existente
                $index = false;
                foreach ($this->offlineData as $idx => $item) {
                    $itemId = $item['id'] ?? $item['uuid'] ?? null;
                    if ($itemId == $id) {
                        $index = $idx;
                        break;
                    }
                }

                if ($index !== false) {
                    // Atualizar existente
                    $this->offlineData[$index] = array_merge($this->offlineData[$index], $data);
                } else {
                    // Adicionar novo
                    $this->offlineData[] = $data;
                }
                break;

            case 'delete':
                $id = $data['id'] ?? $data['uuid'] ?? null;
                if (!$id) break;

                $this->offlineData = array_values(array_filter($this->offlineData, function ($item) use ($id) {
                    $itemId = $item['id'] ?? $item['uuid'] ?? null;
                    return $itemId != $id;
                }));
                break;

            case 'bulk':
                $this->offlineData = is_array($data) ? $data : [];
                break;
        }
    }

    /**
     * Salva um registro offline (create ou update)
     * 
     * @param array $data Dados do registro
     * @param string $action 'create' ou 'update'
     * @param mixed|null $id ID do registro (para update)
     * @return string|null ID temporário gerado (para create) ou ID existente
     */
    public function saveOfflineRecord(array $data, string $action = 'create', $id = null): ?string
    {
        if (!property_exists($this, 'offlineStoreName') || empty($this->offlineStoreName)) {
            Log::error('Tentativa de salvar offline sem $offlineStoreName definido');
            return null;
        }

        // Para create, gerar ID temporário
        if ($action === 'create') {
            $tempId = 'temp-' . time() . '-' . uniqid();
            $data['id'] = $tempId;
        } else {
            // Para update, usar ID fornecido
            if ($id) {
                $data['id'] = $id;
            }
        }

        // Adicionar à fila de sincronização
        $this->dispatch('addToSyncQueue', 
            storeName: $this->offlineStoreName,
            action: $action,
            payload: $data
        );

        Log::info('Registro salvo offline', [
            'component' => static::class,
            'store' => $this->offlineStoreName,
            'action' => $action,
            'id' => $data['id'] ?? 'N/A'
        ]);

        return $data['id'] ?? null;
    }

    /**
     * Deleta um registro offline
     * 
     * @param mixed $id ID do registro
     * @return bool
     */
    public function deleteOfflineRecord($id): bool
    {
        if (!property_exists($this, 'offlineStoreName') || empty($this->offlineStoreName) || !$id) {
            return false;
        }

        $this->dispatch('addToSyncQueue',
            storeName: $this->offlineStoreName,
            action: 'delete',
            payload: ['id' => $id]
        );

        Log::info('Registro deletado offline', [
            'component' => static::class,
            'store' => $this->offlineStoreName,
            'id' => $id
        ]);

        return true;
    }

    /**
     * Atualiza status de conexão
     */
    #[On('connection-status-changed')]
    public function updateConnectionStatus($payload): void
    {
        $this->isOffline = !($payload['online'] ?? true);
        
        if (!$this->isOffline && $this->offlineDataLoaded) {
            // Reconectou - recarregar dados
            $this->loadOfflineData();
        }
    }

    /**
     * Sincronização concluída
     */
    #[On('sync-completed')]
    public function handleSyncCompleted($payload): void
    {
        $this->pendingSyncCount = 0;
        
        // Recarregar dados após sincronização
        $this->loadOfflineData();
        
        // Notificar usuário
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Dados sincronizados com sucesso!'
        ]);
    }

    /**
     * Erro na sincronização
     */
    #[On('sync-error')]
    public function handleSyncError($payload): void
    {
        $error = $payload['error'] ?? 'Erro desconhecido';
        
        Log::warning('Erro na sincronizaçãoo offline', [
            'component' => static::class,
            'error' => $error
        ]);
        
        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Erro ao sincronizar: ' . $error
        ]);
    }

    /**
     * Busca um registro específico nos dados offline
     */
    protected function findOfflineRecord($id): ?array
    {
        foreach ($this->offlineData as $record) {
            $recordId = $record['id'] ?? $record['uuid'] ?? null;
            if ($recordId == $id) {
                return $record;
            }
        }
        
        return null;
    }

    /**
     * Verifica se há dados offline disponí­veis
     */
    public function hasOfflineData(): bool
    {
        return $this->offlineDataLoaded && count($this->offlineData) > 0;
    }
}