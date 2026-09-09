<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleMapsGeocodingService
{
    public function locate(string $address): array
    {
        $key = config('filament-google-maps.keys.server_key');
        if (! $key) {
            throw new RuntimeException('A chave de servidor do Google Maps não está configurada.');
        }

        try {
            $response = Http::connectTimeout(5)->timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $key,
                'language' => 'pt-BR',
                'components' => 'country:BR',
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Não foi possível conectar ao Google Maps. Tente novamente.');
        }

        $status = $response->json('status');
        if (! $response->successful() || $status !== 'OK') {
            Log::warning('Falha na geocodificação Google Maps.', ['http_status' => $response->status(), 'status' => $status]);
            throw new RuntimeException(match ($status) {
                'ZERO_RESULTS' => 'Não foram encontradas coordenadas para este endereço.',
                'REQUEST_DENIED' => 'Consulta recusada pelo Google Maps. Verifique a ativação da Geocoding API, o faturamento e as restrições da chave de servidor no Google Cloud.',
                'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT' => 'O limite de consultas do Google Maps foi atingido. Verifique a cota e o faturamento no Google Cloud.',
                default => 'Não foi possível consultar as coordenadas no Google Maps. Tente novamente.',
            });
        }

        $location = $response->json('results.0.geometry.location');
        if (! is_array($location) || ! is_numeric($location['lat'] ?? null) || ! is_numeric($location['lng'] ?? null)
            || abs((float) $location['lat']) > 90 || abs((float) $location['lng']) > 180) {
            throw new RuntimeException('O Google Maps retornou coordenadas inválidas.');
        }

        return ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng']];
    }
}
