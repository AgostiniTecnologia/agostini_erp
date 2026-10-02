<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_statuses_are_updated_by_the_operational_flow(): void
    {
        $order = $this->order();

        $order->startProduction();
        $this->assertSame(ProductionOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->start_date);

        $order->pauseProduction();
        $this->assertSame(ProductionOrder::STATUS_PAUSED, $order->fresh()->status);

        $order->startProduction();
        $this->assertSame(ProductionOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $order->completeProduction();
        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completion_date);
    }

    public function test_automatically_managed_statuses_cannot_be_set_manually(): void
    {
        foreach (ProductionOrder::automaticallyManagedStatuses() as $status) {
            $order = $this->order();

            try {
                $order->update(['status' => $status]);
                $this->fail("O status {$status} foi alterado manualmente.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
                $this->assertSame('Pendente', $order->fresh()->status);
            }
        }
    }

    private function order(): ProductionOrder
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);

        return ProductionOrder::factory()->for($company)->create([
            'status' => 'Pendente',
            'start_date' => null,
            'completion_date' => null,
            'user_uuid' => $user->uuid,
        ]);
    }
}
