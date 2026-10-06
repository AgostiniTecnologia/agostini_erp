<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\TransportOrder;
use App\Models\TransportOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionToTransportService
{
    /**
     * @return Collection<int, ProductionOrder>
     */
    public function availableOrders(): Collection
    {
        return ProductionOrder::query()
            ->where('status', ProductionOrder::STATUS_COMPLETED)
            ->whereHas('items')
            ->whereDoesntHave('items', function (Builder $items): void {
                $items
                    ->where('quantity_planned', '<=', 0)
                    ->orWhereHas(
                        'transportOrderItems.transportOrder',
                        fn (Builder $transportOrder): Builder => $transportOrder
                            ->where('status', '!=', TransportOrder::STATUS_CANCELLED)
                    );
            })
            ->with([
                'items.product',
                'items.salesOrderItem.salesOrder.client',
            ])
            ->orderBy('completion_date')
            ->orderBy('order_number')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function availableOrderOptions(): array
    {
        return $this->availableOrders()
            ->mapWithKeys(function (ProductionOrder $order): array {
                $firstSalesItem = $order->items->first()?->salesOrderItem;
                $salesOrder = $firstSalesItem?->salesOrder;
                $products = $order->items
                    ->map(fn (ProductionOrderItem $item): string => sprintf(
                        '%s (%s)',
                        $item->product?->name ?? 'Produto removido',
                        $this->formatQuantity($item->quantity_planned)
                    ))
                    ->implode(', ');

                return [
                    $order->uuid => sprintf(
                        '%s | %s | %s | %s',
                        $order->order_number,
                        $salesOrder?->order_number ?? 'Sem pedido',
                        $salesOrder?->client?->name ?? 'Sem cliente',
                        $products
                    ),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $productionOrderUuids
     */
    public function transferTo(
        TransportOrder $transportOrder,
        array $productionOrderUuids,
        ?string $fallbackClientUuid = null
    ): int {
        $productionOrderUuids = array_values(array_unique(array_filter($productionOrderUuids)));

        if ($productionOrderUuids === []) {
            throw ValidationException::withMessages([
                'production_orders' => 'Selecione ao menos uma ordem de produção.',
            ]);
        }

        return DB::transaction(function () use ($transportOrder, $productionOrderUuids, $fallbackClientUuid): int {
            $transportOrder = TransportOrder::query()
                ->whereKey($transportOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($transportOrder->status !== TransportOrder::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'production_orders' => 'Somente ordens de transporte pendentes podem receber produções.',
                ]);
            }

            $fallbackClient = filled($fallbackClientUuid)
                ? Client::query()
                    ->whereKey($fallbackClientUuid)
                    ->where('company_id', $transportOrder->company_id)
                    ->first()
                : null;

            $productionOrders = ProductionOrder::query()
                ->whereIn('uuid', $productionOrderUuids)
                ->lockForUpdate()
                ->get();

            if ($productionOrders->count() !== count($productionOrderUuids)) {
                throw ValidationException::withMessages([
                    'production_orders' => 'Uma das ordens de produção selecionadas não foi encontrada.',
                ]);
            }

            $productionItems = ProductionOrderItem::query()
                ->whereIn('production_order_uuid', $productionOrderUuids)
                ->with(['product', 'salesOrderItem.salesOrder.client'])
                ->lockForUpdate()
                ->get()
                ->groupBy('production_order_uuid');

            $createdItems = 0;

            foreach ($productionOrders as $productionOrder) {
                if ($productionOrder->company_id !== $transportOrder->company_id) {
                    throw ValidationException::withMessages([
                        'production_orders' => 'A produção selecionada pertence a outra empresa.',
                    ]);
                }

                if ($productionOrder->status !== ProductionOrder::STATUS_COMPLETED) {
                    throw ValidationException::withMessages([
                        'production_orders' => "A OP {$productionOrder->order_number} ainda não está concluída.",
                    ]);
                }

                $items = $productionItems->get($productionOrder->uuid, collect());
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'production_orders' => "A OP {$productionOrder->order_number} não possui itens.",
                    ]);
                }

                foreach ($items as $productionItem) {
                    $this->validateProductionItem($productionOrder, $productionItem);

                    $salesOrderItem = $productionItem->salesOrderItem;
                    $salesOrder = $salesOrderItem?->salesOrder;
                    $client = $salesOrder?->client ?? $fallbackClient;

                    if (! $client) {
                        throw ValidationException::withMessages([
                            'fallback_client_id' => "Informe o cliente da OP {$productionOrder->order_number}.",
                        ]);
                    }

                    $alreadyAllocated = TransportOrderItem::query()
                        ->where('production_order_item_id', $productionItem->uuid)
                        ->whereHas(
                            'transportOrder',
                            fn (Builder $query): Builder => $query
                                ->where('status', '!=', TransportOrder::STATUS_CANCELLED)
                        )
                        ->exists();

                    if ($alreadyAllocated) {
                        throw ValidationException::withMessages([
                            'production_orders' => "A OP {$productionOrder->order_number} já foi incluída em outra ordem de transporte ativa.",
                        ]);
                    }

                    $transportOrder->items()->create([
                        'company_id' => $transportOrder->company_id,
                        'client_id' => $client->uuid,
                        'product_id' => $productionItem->product_uuid,
                        'sales_order_item_id' => $salesOrderItem?->uuid,
                        'production_order_item_id' => $productionItem->uuid,
                        'quantity' => $productionItem->quantity_planned,
                        'delivery_address_snapshot' => $client->getFullAddress(),
                        'status' => TransportOrderItem::STATUS_PENDING,
                        'notes' => 'Origem: '.$productionOrder->order_number
                            .($salesOrder ? " / {$salesOrder->order_number}" : ''),
                    ]);

                    $createdItems++;
                }
            }

            return $createdItems;
        }, 3);
    }

    private function validateProductionItem(ProductionOrder $productionOrder, ProductionOrderItem $productionItem): void
    {
        if ($productionItem->salesOrderItem
            && ($productionItem->company_id !== $productionItem->salesOrderItem->company_id
                || $productionItem->company_id !== $productionItem->salesOrderItem->salesOrder?->company_id)) {
            throw ValidationException::withMessages([
                'production_orders' => "A OP {$productionOrder->order_number} possui uma origem de outra empresa.",
            ]);
        }

        if ((float) $productionItem->quantity_planned <= 0) {
            throw ValidationException::withMessages([
                'production_orders' => "A OP {$productionOrder->order_number} possui um item sem quantidade planejada.",
            ]);
        }
    }

    private function formatQuantity(mixed $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 4, ',', '.'), '0'), ',');
    }
}
