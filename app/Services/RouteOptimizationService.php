<?php

namespace App\Services;

use App\Models\TransportOrder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteOptimizationService
{
    protected const GOOGLE_API_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    public function calculateSequence(TransportOrder $order): bool
    {
        $order->loadMissing('items.client', 'company');

        if ($order->items->isEmpty()) {
            return true;
        }

        $company = $order->company;
        if (! $this->hasCoordinates($company)) {
            Log::warning('Empresa sem coordenadas válidas para o cálculo da rota.', ['order_id' => $order->uuid]);

            return $this->applyFallbackSequence($order);
        }

        $clients = $order->items->unique('client_id')->pluck('client');
        if ($clients->contains(fn ($client) => ! $this->hasCoordinates($client))) {
            Log::warning('Cliente sem coordenadas válidas para o cálculo da rota.', ['order_id' => $order->uuid]);

            return $this->applyFallbackSequence($order);
        }

        $coordinates = $clients->mapWithKeys(fn ($client) => [$client->uuid => "{$client->latitude},{$client->longitude}"]);
        $route = $this->findNearestNeighborRoute("{$company->latitude},{$company->longitude}", $coordinates);

        // Preserve todas as sequências existentes se qualquer parada não puder ser calculada.
        if (count($route) !== $coordinates->count()) {
            return $this->applyFallbackSequence($order);
        }

        $this->persistSequence($order, $route);

        return true;
    }

    /**
     * Mantém o fluxo de transporte disponível quando o Google Maps não puder
     * otimizar a rota. A ordem de cadastro dos clientes vira a sequência.
     */
    private function applyFallbackSequence(TransportOrder $order): bool
    {
        if ($order->items->every(fn ($item): bool => filled($item->delivery_sequence))) {
            Log::info('Sequência existente preservada sem o Google Maps.', ['order_id' => $order->uuid]);

            return true;
        }

        $route = $order->items
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($route === []) {
            return false;
        }

        $this->persistSequence($order, $route);
        Log::info('Sequência de cadastro aplicada sem o Google Maps.', ['order_id' => $order->uuid]);

        return true;
    }

    private function persistSequence(TransportOrder $order, array $route): void
    {
        DB::transaction(function () use ($order, $route): void {
            $order->items()->update(['delivery_sequence' => null]);
            foreach ($route as $index => $clientUuid) {
                $order->items()->where('client_id', $clientUuid)->update(['delivery_sequence' => $index + 1]);
            }
        });
    }

    private function hasCoordinates($record): bool
    {
        return $record && is_numeric($record->latitude) && is_numeric($record->longitude)
            && abs((float) $record->latitude) <= 90 && abs((float) $record->longitude) <= 180;
    }

    private function findNearestNeighborRoute(string $startPoint, Collection $clientCoordinateMap): array
    {
        $apiKey = config('filament-google-maps.keys.server_key');
        if (! $apiKey) {
            Log::warning('Chave de servidor do Google Maps não configurada.');

            return [];
        }

        $route = [];
        $remaining = $clientCoordinateMap->all();
        $currentPoint = $startPoint;

        while ($remaining !== []) {
            $shortestDuration = PHP_INT_MAX;
            $nextClientUuid = null;

            // Compare todos os lotes antes de escolher a próxima parada.
            foreach (array_chunk($remaining, 25, true) as $batch) {
                try {
                    $response = Http::connectTimeout(5)->timeout(15)->get(self::GOOGLE_API_URL, [
                        'origins' => $currentPoint,
                        'destinations' => implode('|', $batch),
                        'key' => $apiKey,
                        'mode' => 'driving',
                    ]);
                } catch (ConnectionException $e) {
                    Log::warning('Falha de conexão com Google Distance Matrix.');

                    return [];
                }

                if (! $response->successful() || $response->json('status') !== 'OK') {
                    Log::warning('Google Distance Matrix recusou o cálculo.', [
                        'http_status' => $response->status(),
                        'status' => $response->json('status'),
                    ]);

                    return [];
                }

                $results = $response->json('rows.0.elements');
                if (! is_array($results) || count($results) !== count($batch)) {
                    return [];
                }

                $uuids = array_keys($batch);
                foreach ($results as $index => $result) {
                    $duration = $result['duration']['value'] ?? null;
                    if (($result['status'] ?? null) === 'OK' && is_numeric($duration) && $duration >= 0 && $duration < $shortestDuration) {
                        $shortestDuration = $duration;
                        $nextClientUuid = $uuids[$index];
                    }
                }
            }

            if ($nextClientUuid === null) {
                Log::warning('Não foi possível calcular uma rota completa.');

                return [];
            }

            $route[] = $nextClientUuid;
            $currentPoint = $remaining[$nextClientUuid];
            unset($remaining[$nextClientUuid]);
        }

        return $route;
    }
}
