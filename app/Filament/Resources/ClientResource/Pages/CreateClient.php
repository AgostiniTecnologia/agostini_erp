<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Livewire\Traits\LivewireOfflineData;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class CreateClient extends CreateRecord
{
    use \App\Filament\Concerns\GeocodesAddress;
    use LivewireOfflineData;

    protected static string $resource = ClientResource::class;

    public string $offlineStoreName = 'clients';

    public bool $isLoadingCnpj = false;

    public bool $isLoadingCep = false;

    public bool $isOffline = false;

    public function mount(): void
    {
        parent::mount();
        $this->loadOfflineData();
    }

    /**
     * Hook ANTES de criar o registro
     * Aqui interceptamos para salvar offline se necessário
     */
    protected function beforeCreate(): void
    {
        // Se estiver offline, salvar localmente
        if (! $this->checkOnlineStatus()) {
            $this->handleOfflineCreate();
        }
    }

    /**
     * Hook APÓS criar o registro
     * Adiciona ao cache offline quando criado online
     */
    protected function afterCreate(): void
    {
        // Se criou online, adicionar ao cache offline também
        if ($this->checkOnlineStatus() && $this->record) {
            $this->dispatch('saveOfflineData',
                storeName: 'clients',
                item: $this->record->toArray()
            );

            Log::info('Cliente criado e cacheado offline', [
                'client_id' => $this->record->id,
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

        Log::info('Cliente salvo offline', [
            'temp_id' => $tempId,
            'data' => $data,
        ]);

        Notification::make()
            ->title('Cliente salvo offline')
            ->body('Será sincronizado automaticamente quando reconectar.')
            ->success()
            ->send();

        // Redirecionar para lista
        $this->redirect(static::getResource()::getUrl('index'));

        // Cancelar criação normal (já salvou offline)
        $this->halt();
    }

    /**
     * Verifica se está online
     */
    protected function checkOnlineStatus(): bool
    {
        return ! $this->isOffline;
    }

    #[On('fetchCnpjClientData')]
    public function fetchCnpjClientData(string $cnpj): void
    {
        if (empty($cnpj)) {
            Notification::make()
                ->title('CNPJ não informado')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->isLoadingCnpj = true;

            $response = Http::timeout(10)->get("https://publica.cnpj.ws/cnpj/{$cnpj}");

            if ($response->failed()) {
                $status = $response->status();

                $msg = match ($status) {
                    404 => 'CNPJ não encontrado.',
                    429 => 'Limite de requisições excedido. Tente novamente mais tarde.',
                    default => "Erro ao consultar o CNPJ ({$status})."
                };

                Notification::make()
                    ->title('Erro ao consultar CNPJ')
                    ->body($msg)
                    ->danger()
                    ->send();

                return;
            }

            $data = $response->json();

            if (($data['status'] ?? null) == 404) {
                Notification::make()
                    ->title('CNPJ inválido')
                    ->body($data['titulo'] ?? 'O CNPJ informado é inválido.')
                    ->warning()
                    ->send();

                return;
            }

            $estado = $this->form->getRawState();

            $latitude = $data['estabelecimento']['latitude'] ?? null;
            $longitude = $data['estabelecimento']['longitude'] ?? null;
            $stateRegistration = collect($data['estabelecimento']['inscricoes_estaduais'] ?? [])
                ->firstWhere('ativo', true)['inscricao_estadual'] ?? null;

            $newData = [
                'social_name' => $data['razao_social'] ?? null,
                'taxNumber' => $data['estabelecimento']['cnpj'] ?? $cnpj,
                'name' => $data['estabelecimento']['nome_fantasia'] ?? $data['razao_social'],
                'state_registration' => $stateRegistration,
                'email' => $data['estabelecimento']['email'] ?? null,
                'phone_number' => $this->formatPhoneNumber($data['estabelecimento'] ?? []),

                'address_street' => $data['estabelecimento']['logradouro'] ?? null,
                'address_number' => $data['estabelecimento']['numero'] ?? null,
                'address_complement' => $data['estabelecimento']['complemento'] ?? null,
                'address_district' => $data['estabelecimento']['bairro'] ?? null,
                'address_city' => $data['estabelecimento']['cidade']['nome'] ?? null,
                'address_state' => $data['estabelecimento']['estado']['sigla'] ?? null,
                'address_zip_code' => preg_replace('/[^0-9]/', '', $data['estabelecimento']['cep'] ?? ''),

                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            $this->form->fill(array_merge($estado, $newData));

            Notification::make()
                ->title('Dados preenchidos!')
                ->body('Consulta ao CNPJ realizada com sucesso.')
                ->success()
                ->send();

            if (is_numeric($latitude) && is_numeric($longitude)) {
                $this->data['map_visualization'] = ['lat' => (float) $latitude, 'lng' => (float) $longitude];
            } else {
                $this->geocodeAddressAndFillCoordinates();
            }

        } catch (\Exception $e) {
            Log::error('Erro ao consultar CNPJ: '.$e->getMessage());

            Notification::make()
                ->title('Erro ao consultar CNPJ')
                ->body('Erro inesperado. Verifique os logs.')
                ->danger()
                ->send();
        } finally {
            $this->isLoadingCnpj = false;
        }
    }

    protected function formatPhoneNumber(array $estab): ?string
    {
        $ddd = $estab['ddd1'] ?? $estab['ddd'] ?? null;
        $fone = $estab['telefone1'] ?? $estab['telefone'] ?? null;

        if ($ddd && $fone) {
            return preg_replace('/[^0-9]/', '', $ddd.$fone);
        }

        return null;
    }

    #[On('fetchCepData')]
    public function fetchCepData(string $cep): void
    {
        if (empty($cep)) {
            Notification::make()
                ->title('CEP não informado')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->isLoadingCep = true;

            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->failed()) {
                Notification::make()
                    ->title('Erro ao consultar CEP')
                    ->body("Falha ao consultar CEP ({$response->status()}).")
                    ->danger()
                    ->send();

                return;
            }

            $data = $response->json();

            if (! empty($data['erro'])) {
                Notification::make()
                    ->title('CEP não encontrado')
                    ->warning()
                    ->send();

                return;
            }

            $estadoAtual = $this->form->getRawState();

            $novo = [
                'address_street' => $data['logradouro'] ?? null,
                'address_complement' => $data['complemento'] ?? null,
                'address_district' => $data['bairro'] ?? null,
                'address_city' => $data['localidade'] ?? null,
                'address_state' => $data['uf'] ?? null,
            ];

            $this->form->fill(array_merge($estadoAtual, $novo));

            Notification::make()
                ->title('Endereço carregado!')
                ->body('Consulta ao CEP realizada com sucesso.')
                ->success()
                ->send();

            $this->geocodeAddressAndFillCoordinates();

        } catch (\Exception $e) {
            Log::error('Erro ao consultar CEP: '.$e->getMessage());

            Notification::make()
                ->title('Erro ao consultar CEP')
                ->body('Erro inesperado. Verifique os logs.')
                ->danger()
                ->send();

        } finally {
            $this->isLoadingCep = false;
        }
    }
}
