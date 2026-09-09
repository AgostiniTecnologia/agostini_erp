<?php

namespace Tests\Feature;

use App\Livewire\DriverDeliveryManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\TransportOrder;
use App\Models\TransportOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TransportOrderWithoutGoogleMapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_order_pdf_is_generated_without_google_or_delivery_sequence(): void
    {
        [$user, $order, $item] = $this->transportScenario(TransportOrder::STATUS_APPROVED);
        config(['filament-google-maps.keys.server_key' => null]);

        $this->assertNull($item->delivery_sequence);

        $response = $this->actingAs($user)->get(route('transport-orders.pdf', $order->uuid));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_qr_scan_and_delivery_photo_work_without_google_maps(): void
    {
        Storage::fake('public');
        [$user, $order, $item] = $this->transportScenario(TransportOrder::STATUS_IN_PROGRESS);
        config(['filament-google-maps.keys.server_key' => null]);

        $component = Livewire::actingAs($user)
            ->test(DriverDeliveryManager::class)
            ->set('scannedQrCodeData', $item->uuid)
            ->call('processQrCodeScan')
            ->assertSet('showPhotoUploadModal', true)
            ->set('uploadedPhotos', [UploadedFile::fake()->image('entrega.jpg')])
            ->call('savePhotosAndProceed')
            ->assertSet('showConfirmationModal', true);

        $photoPath = $item->fresh()->delivery_photos[0] ?? null;

        $this->assertNotNull($photoPath);
        Storage::disk('public')->assertExists($photoPath);
        $component->call('confirmDelivery', true);
        $this->assertSame(TransportOrderItem::STATUS_COMPLETED, $item->fresh()->status);
        $this->assertSame(TransportOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    private function transportScenario(string $status): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['is_active' => true]);
        $client = Client::query()->create([
            'company_id' => $company->uuid,
            'name' => 'Cliente sem mapa',
            'social_name' => 'Cliente sem mapa Ltda.',
            'taxNumber' => '12345678000190',
            'address_street' => 'Rua de Testes',
            'address_number' => '10',
        ]);
        $product = Product::factory()->forCompany($company)->create();
        $order = TransportOrder::query()->create([
            'company_id' => $company->uuid,
            'driver_id' => $user->uuid,
            'status' => $status,
        ]);
        $item = TransportOrderItem::query()->create([
            'company_id' => $company->uuid,
            'transport_order_id' => $order->uuid,
            'client_id' => $client->uuid,
            'product_id' => $product->uuid,
            'quantity' => 1,
            'delivery_address_snapshot' => 'Rua de Testes, 10',
            'delivery_sequence' => null,
        ]);

        return [$user, $order, $item];
    }
}
