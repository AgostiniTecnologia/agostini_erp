<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class CreateCompany extends CreateRecord
{
    use \App\Filament\Concerns\GeocodesAddress;

    protected static string $resource = CompanyResource::class;

    public bool $isLoadingCnpj = false;

    public bool $isLoadingCep = false;

    #[On('fetchCnpjCompanyData')]
    public function fetchCnpjCompanyData(string $cnpj): void
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
                $errorMessage = "Falha ao consultar o CNPJ (HTTP {$status}).";
                if ($status === 404) {
                    $errorMessage = 'CNPJ não encontrado na base de dados.';
                } elseif ($status === 429) {
                    $errorMessage = 'Muitas solicitações. Aguarde um momento e tente novamente.';
                } elseif ($response->json('detalhes')) {
                    $errorMessage = $response->json('detalhes');
                }

                Notification::make()
                    ->title('Erro na Consulta de CNPJ')
                    ->body($errorMessage)
                    ->danger()
                    ->send();

                return;
            }

            $data = $response->json();

            if (isset($data['status']) && $data['status'] == 404) {
                Notification::make()
                    ->title('CNPJ Inválido ou Não Encontrado')
                    ->body($data['titulo'] ?? 'O CNPJ informado não foi encontrado ou é inválido.')
                    ->warning()
                    ->send();

                return;
            }

            $currentFormData = $this->form->getRawState();
            $newLat = $data['estabelecimento']['latitude'] ?? null;
            $newLng = $data['estabelecimento']['longitude'] ?? null;

            $newData = [
                'social_name' => $data['razao_social'] ?? null,
                'taxNumber' => $data['estabelecimento']['cnpj'] ?? $cnpj,
                'name' => $data['nome_fantasia'] ?? $data['razao_social'] ?? null,
                'email' => $data['estabelecimento']['email'] ?? null,
                'phone_number' => $this->formatPhoneNumber($data['estabelecimento'] ?? []),
                'address_street' => $data['estabelecimento']['logradouro'] ?? null,
                'address_number' => $data['estabelecimento']['numero'] ?? null,
                'address_complement' => $data['estabelecimento']['complemento'] ?? null,
                'address_district' => $data['estabelecimento']['bairro'] ?? null,
                'address_city' => $data['estabelecimento']['cidade']['nome'] ?? null,
                'address_state' => $data['estabelecimento']['estado']['sigla'] ?? null,
                'address_zip_code' => preg_replace('/[^0-9]/', '', $data['estabelecimento']['cep'] ?? ''),
                'latitude' => $newLat, // Usar as variáveis
                'longitude' => $newLng, // Usar as variáveis
            ];
            $this->form->fill(array_merge($currentFormData, $newData));

            Notification::make()
                ->title('CNPJ Consultado')
                ->body('Dados preenchidos com base na consulta.')
                ->success()
                ->send();

            if (is_numeric($newLat) && is_numeric($newLng)) {
                $this->data['map_visualization'] = ['lat' => (float) $newLat, 'lng' => (float) $newLng];
            } else {
                $this->geocodeAddressAndFillCoordinates();
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Notification::make()
                ->title('Erro de Conexão (CNPJ)')
                ->body('Não foi possível conectar ao serviço de consulta de CNPJ.')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Log::error('Erro na consulta de CNPJ: '.$e->getMessage(), ['exception' => $e]);
            Notification::make()
                ->title('Erro na Consulta de CNPJ')
                ->body('Ocorreu um erro inesperado. Consulte os logs para mais detalhes.')
                ->danger()
                ->send();
        } finally {
            $this->isLoadingCnpj = false;
        }
    }

    protected function formatPhoneNumber(array $estabelecimentoData): ?string
    {
        $ddd = $estabelecimentoData['ddd1'] ?? $estabelecimentoData['ddd'] ?? null;
        $phone = $estabelecimentoData['telefone1'] ?? $estabelecimentoData['telefone'] ?? null;

        if ($ddd && $phone) {
            return preg_replace('/[^0-9]/', '', $ddd.$phone);
        }

        return null;
    }

    #[On('fetchCompanyCepData')]
    public function fetchCompanyCepData(string $cep): void
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
                    ->title('Erro na Consulta de CEP')
                    ->body("Falha ao consultar o CEP (HTTP {$response->status()}).")
                    ->danger()
                    ->send();

                return;
            }

            $data = $response->json();

            if (isset($data['erro']) && $data['erro'] === true) {
                Notification::make()
                    ->title('CEP Não Encontrado')
                    ->body('O CEP informado não foi encontrado na base de dados.')
                    ->warning()
                    ->send();

                return;
            }

            $currentFormData = $this->form->getRawState();
            $newData = [
                'address_street' => $data['logradouro'] ?? null,
                'address_complement' => $data['complemento'] ?? null,
                'address_district' => $data['bairro'] ?? null,
                'address_city' => $data['localidade'] ?? null,
                'address_state' => $data['uf'] ?? null,
                // address_zip_code já foi preenchido pelo usuário
            ];
            $this->form->fill(array_merge($currentFormData, $newData));

            Notification::make()
                ->title('CEP Consultado')
                ->body('Endereço preenchido com base na consulta do CEP.')
                ->success()
                ->send();

            // Tentar geocodificar o endereço APÓS preencher com dados do CEP
            $this->geocodeAddressAndFillCoordinates();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Notification::make()
                ->title('Erro de Conexão (CEP)')
                ->body('Não foi possível conectar ao serviço de consulta de CEP.')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Log::error('Erro na consulta de CEP: '.$e->getMessage(), ['exception' => $e]);
            Notification::make()
                ->title('Erro na Consulta de CEP')
                ->body('Ocorreu um erro inesperado. Consulte os logs para mais detalhes.')
                ->danger()
                ->send();
        } finally {
            $this->isLoadingCep = false;
        }
    }
}
