<?php

namespace App\Filament\Concerns;

use App\Services\GoogleMapsGeocodingService;
use Filament\Notifications\Notification;
use RuntimeException;

trait GeocodesAddress
{
    protected function geocodeAddressAndFillCoordinates(): void
    {
        $data = $this->form->getRawState();
        if (empty($data['address_street']) || empty($data['address_city']) || empty($data['address_state'])) {
            return;
        }

        $address = implode(', ', array_filter([
            $data['address_street'], $data['address_number'] ?? null,
            $data['address_district'] ?? null, $data['address_city'],
            $data['address_state'], $data['address_zip_code'] ?? null, 'Brasil',
        ], fn ($value) => $value !== null && $value !== ''));

        try {
            $location = app(GoogleMapsGeocodingService::class)->locate($address);
        } catch (RuntimeException $e) {
            Notification::make()->title('Não foi possível localizar o endereço')->body($e->getMessage())->warning()->send();

            return;
        }

        $this->data['latitude'] = $location['lat'];
        $this->data['longitude'] = $location['lng'];
        $this->data['map_visualization'] = $location;
        Notification::make()->title('Coordenadas atualizadas')->success()->send();
    }
}
