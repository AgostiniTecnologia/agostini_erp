<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Company;
use App\Models\TransportOrder;
use App\Models\TransportOrderItem;
use App\Services\GoogleMapsGeocodingService;
use App\Services\RouteOptimizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleMapsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-google-maps.keys.server_key' => 'server-test-key', 'filament-google-maps.keys.web_key' => 'browser-test-key']);
        Http::preventStrayRequests();
    }

    private function order(int $count = 2): TransportOrder
    {
        $order = Mockery::mock(TransportOrder::class)->makePartial();
        $order->setRelation('company', new Company(['latitude' => 0, 'longitude' => -46]));
        $items = new Collection;
        for ($i = 1; $i <= $count; $i++) {
            $client = new Client(['latitude' => -20 - $i / 100, 'longitude' => -46]);
            $client->uuid = 'client-'.$i;
            $item = new TransportOrderItem(['client_id' => $client->uuid, 'delivery_sequence' => $i]);
            $item->setRelation('client', $client);
            $items->push($item);
        }
        $order->setRelation('items', $items);

        return $order;
    }

    public function test_missing_key_preserves_existing_sequence_without_requesting_google(): void
    {
        config(['filament-google-maps.keys.server_key' => null]);
        $order = $this->order();
        $order->shouldNotReceive('items');
        $this->assertTrue((new RouteOptimizationService)->calculateSequence($order));
        Http::assertNothingSent();
    }

    public function test_missing_key_assigns_registration_order_when_sequence_is_empty(): void
    {
        config(['filament-google-maps.keys.server_key' => null]);
        $order = $this->order();
        $order->items->each(fn (TransportOrderItem $item) => $item->delivery_sequence = null);
        $relation = Mockery::mock(HasMany::class);
        $order->shouldReceive('items')->andReturn($relation);
        $relation->shouldReceive('update')->once()->with(['delivery_sequence' => null])->andReturn(2);
        $relation->shouldReceive('where')->with('client_id', 'client-1')->once()->andReturnSelf();
        $relation->shouldReceive('where')->with('client_id', 'client-2')->once()->andReturnSelf();
        $relation->shouldReceive('update')->with(['delivery_sequence' => 1])->once()->andReturn(1);
        $relation->shouldReceive('update')->with(['delivery_sequence' => 2])->once()->andReturn(1);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());

        $this->assertTrue((new RouteOptimizationService)->calculateSequence($order));
        Http::assertNothingSent();
    }

    public function test_partial_route_preserves_existing_sequences(): void
    {
        Http::fakeSequence()->push(['status' => 'OK', 'rows' => [['elements' => [
            ['status' => 'OK', 'duration' => ['value' => 10]], ['status' => 'ZERO_RESULTS'],
        ]]]])->push(['status' => 'OK', 'rows' => [['elements' => [['status' => 'ZERO_RESULTS']]]]]);
        $order = $this->order();
        $order->shouldNotReceive('items');
        $this->assertTrue((new RouteOptimizationService)->calculateSequence($order));
        $this->assertSame([1, 2], $order->items->pluck('delivery_sequence')->all());
    }

    public function test_connection_failure_does_not_update_deliveries(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $order = $this->order();
        $order->shouldNotReceive('items');
        $this->assertTrue((new RouteOptimizationService)->calculateSequence($order));
    }

    public function test_invalid_coordinates_are_rejected_before_request(): void
    {
        $order = $this->order();
        $order->items[0]->client->latitude = null;
        $this->assertTrue((new RouteOptimizationService)->calculateSequence($order));
        Http::assertNothingSent();
    }

    public function test_batches_choose_nearest_across_all_destinations_and_use_order_company(): void
    {
        $origins = [];
        Http::fake(function ($request) use (&$origins) {
            $origins[] = $request['origins'];
            $destinations = explode('|', $request['destinations']);
            $this->assertLessThanOrEqual(25, count($destinations));
            $this->assertSame('server-test-key', $request['key']);

            return Http::response(['status' => 'OK', 'rows' => [['elements' => array_map(fn ($destination) => [
                'status' => 'OK', 'duration' => ['value' => str_starts_with($destination, '-20.2600000,') ? 1 : 100],
            ], $destinations)]]]);
        });
        $order = $this->order(26);
        $relation = Mockery::mock(HasMany::class);
        $order->shouldReceive('items')->andReturn($relation);
        $relation->shouldReceive('update')->once()->with(['delivery_sequence' => null])->andReturn(26);
        $sequence = [];
        $relation->shouldReceive('where')->with('client_id', Mockery::on(function ($uuid) use (&$sequence) {
            $sequence[] = $uuid;

            return true;
        }))->andReturnSelf();
        $relation->shouldReceive('update')->with(Mockery::on(fn ($data) => is_int($data['delivery_sequence'])))->times(26)->andReturn(1);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        $this->assertTrue((new RouteOptimizationService)->calculateSequence($order));
        $this->assertSame('0,-46', $origins[0]);
        $this->assertSame('0,-46', $origins[1]);
        $this->assertSame('client-26', $sequence[0]);
        $this->assertCount(26, array_unique($sequence));
    }

    public function test_malformed_matrix_falls_back_to_existing_sequence(): void
    {
        Http::fake(fn () => Http::response(['status' => 'OK']));
        $this->assertTrue((new RouteOptimizationService)->calculateSequence($this->order()));
    }

    public function test_geocoding_uses_server_key_and_returns_numeric_location(): void
    {
        Http::fake(fn () => Http::response(['status' => 'OK', 'results' => [['geometry' => ['location' => ['lat' => '0', 'lng' => '-46']]]]]));
        $this->assertSame(['lat' => 0.0, 'lng' => -46.0], (new GoogleMapsGeocodingService)->locate('São Paulo, Brasil'));
        Http::assertSent(fn ($request) => $request['key'] === 'server-test-key' && $request['components'] === 'country:BR');
    }

    public function test_geocoding_denial_explains_configuration_failure(): void
    {
        Http::fake(fn () => Http::response(['status' => 'REQUEST_DENIED']));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Geocoding API');
        (new GoogleMapsGeocodingService)->locate('São Paulo');
    }

    public function test_geocoding_rejects_invalid_success_response(): void
    {
        Http::fake(fn () => Http::response(['status' => 'OK', 'results' => []]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('coordenadas inválidas');
        (new GoogleMapsGeocodingService)->locate('São Paulo');
    }
}
