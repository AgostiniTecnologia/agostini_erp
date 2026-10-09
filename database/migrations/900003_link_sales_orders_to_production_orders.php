<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreignUuid('sales_order_id')
                ->nullable()
                ->after('company_id')
                ->constrained('sales_orders', 'uuid')
                ->nullOnDelete();

            $table->unique('sales_order_id');
        });

        $links = DB::table('production_order_items as production_item')
            ->join('sales_order_items as sales_item', 'sales_item.uuid', '=', 'production_item.sales_order_item_id')
            ->whereNull('production_item.deleted_at')
            ->select('production_item.production_order_uuid', 'sales_item.sales_order_id')
            ->distinct()
            ->orderBy('production_item.production_order_uuid')
            ->get();

        $linkedSalesOrders = [];

        foreach ($links as $link) {
            if (isset($linkedSalesOrders[$link->sales_order_id])) {
                continue;
            }

            DB::table('production_orders')
                ->where('uuid', $link->production_order_uuid)
                ->whereNull('sales_order_id')
                ->update(['sales_order_id' => $link->sales_order_id]);

            $linkedSalesOrders[$link->sales_order_id] = true;
        }
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropUnique(['sales_order_id']);
            $table->dropConstrainedForeignId('sales_order_id');
        });
    }
};
