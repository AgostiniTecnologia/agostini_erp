<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('products', 'products_sku_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_sku_unique');
            });
        }

        if (! Schema::hasIndex('products', 'products_company_sku_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['company_id', 'sku'], 'products_company_sku_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('products', 'products_company_sku_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_company_sku_unique');
            });
        }

        if (! Schema::hasIndex('products', 'products_sku_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('sku');
            });
        }
    }
};
