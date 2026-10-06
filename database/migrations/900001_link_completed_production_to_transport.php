<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This guard also lets the migration resume safely after a partial
        // MySQL execution from the previous version of this migration.
        if (! Schema::hasColumn('production_order_items', 'sales_order_item_id')) {
            Schema::table('production_order_items', function (Blueprint $table) {
                $table->foreignUuid('sales_order_item_id')
                    ->nullable()
                    ->after('production_order_uuid')
                    ->constrained('sales_order_items', 'uuid')
                    ->nullOnDelete();

                $table->index('sales_order_item_id');
            });
        }

        // MySQL may use the old unique index to support the transport_order_id
        // foreign key. Create its replacement in a separate ALTER first.
        if (! Schema::hasIndex('transport_order_items', 'transport_item_order_client_index')) {
            Schema::table('transport_order_items', function (Blueprint $table) {
                $table->index(
                    ['transport_order_id', 'client_id'],
                    'transport_item_order_client_index'
                );
            });
        }

        if (Schema::hasIndex('transport_order_items', 'transport_item_client_product_unique')) {
            Schema::table('transport_order_items', function (Blueprint $table) {
                $table->dropUnique('transport_item_client_product_unique');
            });
        }

        $this->backfillSalesOrderItems();
    }

    public function down(): void
    {
        if (! Schema::hasIndex('transport_order_items', 'transport_item_client_product_unique')) {
            Schema::table('transport_order_items', function (Blueprint $table) {
                $table->unique(
                    ['transport_order_id', 'client_id', 'product_id'],
                    'transport_item_client_product_unique'
                );
            });
        }

        if (Schema::hasIndex('transport_order_items', 'transport_item_order_client_index')) {
            Schema::table('transport_order_items', function (Blueprint $table) {
                $table->dropIndex('transport_item_order_client_index');
            });
        }

        if (Schema::hasColumn('production_order_items', 'sales_order_item_id')) {
            Schema::table('production_order_items', function (Blueprint $table) {
                $table->dropForeign(['sales_order_item_id']);
                $table->dropIndex(['sales_order_item_id']);
                $table->dropColumn('sales_order_item_id');
            });
        }
    }

    /**
     * Recover the structured origin of production orders created by the existing
     * sales workflow. Unmatched/manual production orders remain available for
     * manual transport entry.
     */
    private function backfillSalesOrderItems(): void
    {
        DB::table('production_order_items as production_item')
            ->join('production_orders as production_order', 'production_order.uuid', '=', 'production_item.production_order_uuid')
            ->whereNull('production_item.sales_order_item_id')
            ->select([
                'production_item.uuid',
                'production_item.product_uuid',
                'production_order.company_id',
                'production_order.notes',
            ])
            ->orderBy('production_item.uuid')
            ->chunk(500, function ($productionItems): void {
                foreach ($productionItems as $productionItem) {
                    if (! preg_match('/Pedido de Venda:\s*([^\s]+)/i', (string) $productionItem->notes, $matches)) {
                        continue;
                    }

                    $salesOrder = DB::table('sales_orders')
                        ->where('company_id', $productionItem->company_id)
                        ->where('order_number', $matches[1])
                        ->whereNull('deleted_at')
                        ->first(['uuid']);

                    if (! $salesOrder) {
                        continue;
                    }

                    $salesOrderItem = DB::table('sales_order_items')
                        ->where('sales_order_id', $salesOrder->uuid)
                        ->where('product_id', $productionItem->product_uuid)
                        ->first(['uuid']);

                    if ($salesOrderItem) {
                        DB::table('production_order_items')
                            ->where('uuid', $productionItem->uuid)
                            ->update(['sales_order_item_id' => $salesOrderItem->uuid]);
                    }
                }
            });
    }
};
