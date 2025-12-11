<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    public bool $isLoadingCnpj = false;
    public bool $isLoadingCep  = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    #[On('fetchCnpjClientData')]
    public function fetchCnpjClientData(string $cnpj): void
    {
        if (!$cnpj) {
            Notification::make()
                ->title('CNPJ não informado')
                ->warning()
                ->send();
            return;
        }

        try {
            $this->isLoadingCnpj = true;

            $response = Http::timeout(12)->get("https://publica.cnpj.ws/cnpj/{$cnpj}");

            if ($response->failed()) {
                $status = $response->status();

                $msg = match ($status) {
                    404     => 'CNPJ não encontrado.',
                    429     => 'Limite de consultas excedido. Aguarde um pouco.',
                    default => "Falha ao consultar CNPJ ({$status})."
                };

                Notification::make()
                    ->title('Erro ao consultar CNPJ')
                    ->body($msg)
                    ->danger()
                    ->send();

                return;
            }

            $json = $response->json();

            if (($json['status'] ?? null) == 404) {
                Notification::make()
                    ->title('CNPJ inválido')
                    ->warning()
                    ->send();
                return;
            }

            $form = $this->form->getState();

            $lat = $json['estabelecimento']['latitude']  ?? null;
            $lng = $json['estabelecimento']['longitude'] ?? null;

            $dados = [
                'social_name'        => $json['razao_social'] ?? $form['social_name'],
                'name'               => $json['nome_fantasia'] ?? $json['razao_social'] ?? $form['name'],
                'email'              => $json['estabelecimento']['email'] ?? $form['email'],
                'phone_number'       => $this->formatPhone($json['estabelecimento'] ?? []),

                'address_street'     => $json['estabelecimento']['logradouro'] ?? null,
                'address_number'     => $json['estabelecimento']['numero']     ?? null,
                'address_complement' => $json['estabelecimento']['complemento']?? null,
                'address_district'   => $json['estabelecimento']['bairro']     ?? null,
                'address_city'       => $json['estabelecimento']['cidade']['nome'] ?? null,
                'address_state'      => $json['estabelecimento']['estado']['sigla'] ?? null,

                'address_zip_code'   => preg_replace('/[^0-9]/', '', $json['estabelecimento']['cep'] ?? ''),

                'latitude'           => $lat ?? $form['latitude'],
                'longitude'          => $lng ?? $form['longitude'],
            ];

            $this->form->fill(array_merge($form, $dados));

            Notification::make()
                ->title('Dados carregados!')
                ->body('Consulta ao CNPJ concluída com sucesso.')
                ->success()
                ->send();

            if ($lat && $lng) {
                $this->dispatch('updateMapLocation', lat: (float)$lat, lng: (float)$lng, target: 'map_visualization');
            } else {
                $this->geocodeAddressAndFillCoordinates();
            }

        } catch (\Exception $e) {
            Log::error('Erro ao consultar CNPJ (EditClient): ' . $e->getMessage());

            Notification::make()
                ->title('Erro ao consultar CNPJ')
                ->body('Ocorreu um erro inesperado.')
                ->danger()
                ->send();

        } finally {
            $this->isLoadingCnpj = false;
        }
    }


    protected function formatPhone(array $data): ?string
    {
        $ddd  = $data['ddd1']      ?? $data['ddd']      ?? null;
        $fone = $data['telefone1'] ?? $data['telefone'] ?? null;

        if ($ddd && $fone) {
            return preg_replace('/[^0-9]/', '', $ddd . $fone);
        }

        return null;
    }


    #[On('fetchCepData')]
    public function fetchCepData(string $cep): void
    {
        if (!$cep) {
            Notification::make()
                ->title('CEP não informado')
                ->warning()
                ->send();
            return;
        }

        try {
            $this->isLoadingCep = true;

            $response = Http::timeout(8)->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->failed()) {
                Notification::make()
                    ->title('Erro ao consultar CEP')
                    ->body("Falha ao consultar CEP ({$response->status()}).")
                    ->danger()
                    ->send();
                return;
            }

            $json = $response->json();

            if (!empty($json['erro'])) {
                Notification::make()
                    ->title('CEP não encontrado')
                    ->warning()
                    ->send();
                return;
            }

            $form = $this->form->getState();

            $dados = [
                'address_street'     => $json['logradouro'] ?? $form['address_street'],
                'address_complement' => $json['complemento'] ?? $form['address_complement'],
                'address_district'   => $json['bairro'] ?? $form['address_district'],
                'address_city'       => $json['localidade'] ?? $form['address_city'],
                'address_state'      => $json['uf'] ?? $form['address_state'],
            ];

            $this->form->fill(array_merge($form, $dados));

            Notification::make()
                ->title('Endereço carregado!')
                ->body('Dados do CEP aplicados.')
                ->success()
                ->send();

            $this->geocodeAddressAndFillCoordinates();

        } catch (\Exception $e) {
            Log::error('Erro ao consultar CEP (EditClient): ' . $e->getMessage());

            Notification::make()
                ->title('Erro ao consultar CEP')
                ->body('Erro inesperado.')
                ->danger()
                ->send();

        } finally {
            $this->isLoadingCep = false;
        }
    }


    protected function geocodeAddressAndFillCoordinates(): void
    {
        $f = $this->form->getState();

        $rua   = $f['address_street']  ?? '';
        $num   = $f['address_number']  ?? '';
        $bairro= $f['address_district']?? '';
        $cidade= $f['address_city']    ?? '';
        $estado= $f['address_state']   ?? '';
        $cep   = preg_replace('/[^0-9]/', '', $f['address_zip_code'] ?? '');

        if (!$rua || !$cidade || !$estado) {
            return;
        }

        $partes = array_filter([$rua, $num, $bairro, $cidade, $estado, $cep]);
        $endereco = implode(', ', $partes);

        $key = config('filament-google-maps.key');

        if (!$key) {
            Log::warning('API KEY Google Maps ausente em EditClient.');
            return;
        }

        try {
            $resp = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $endereco,
                'key'     => $key,
                'language'=> 'pt-BR'
            ]);

            if ($resp->failed()) {
                return;
            }

            $geo = $resp->json();

            if (($geo['status'] ?? '') !== 'OK') {
                return;
            }

            $loc = $geo['results'][0]['geometry']['location'];

            $f['latitude']  = $loc['lat'];
            $f['longitude'] = $loc['lng'];

            $this->form->fill($f);

            $this->dispatch('updateMapLocation', lat: (float)$loc['lat'], lng: (float)$loc['lng'], target: 'map_visualization');

        } catch (\Exception $e) {
            Log::error('Erro ao geocodificar (EditClient): ' . $e->getMessage());
        }
    }
}
