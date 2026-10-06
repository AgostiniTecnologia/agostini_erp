<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transport_order_items', 'production_order_item_id')) {
            Schema::table('transport_order_items', function (Blueprint $table) {
                $table->foreignUuid('production_order_item_id')
                    ->nullable()
                    ->after('sales_order_item_id')
                    ->constrained('production_order_items', 'uuid')
                    ->nullOnDelete();

                $table->index('production_order_item_id');
            });
        }

        DB::table('transport_order_items')
            ->whereNull('production_order_item_id')
            ->whereNotNull('sales_order_item_id')
            ->orderBy('uuid')
            ->chunk(500, function ($transportItems): void {
                foreach ($transportItems as $transportItem) {
                    $productionItem = DB::table('production_order_items')
                        ->where('sales_order_item_id', $transportItem->sales_order_item_id)
                        ->whereNull('deleted_at')
                        ->first(['uuid']);

                    if ($productionItem) {
                        DB::table('transport_order_items')
                            ->where('uuid', $transportItem->uuid)
                            ->update(['production_order_item_id' => $productionItem->uuid]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('transport_order_items', 'production_order_item_id')) {
            Schema::table('transport_order_items', function (Blueprint $table) {
                $table->dropForeign(['production_order_item_id']);
                $table->dropIndex(['production_order_item_id']);
                $table->dropColumn('production_order_item_id');
            });
        }
    }
};
