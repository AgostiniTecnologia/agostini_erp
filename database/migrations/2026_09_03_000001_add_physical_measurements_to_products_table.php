<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight_net', 12, 3)->nullable()->after('stock');
            $table->decimal('weight', 12, 3)->nullable()->after('weight_net');
            $table->decimal('length', 12, 3)->nullable()->after('weight');
            $table->decimal('width', 12, 3)->nullable()->after('length');
            $table->decimal('height', 12, 3)->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight_net', 'weight', 'length', 'width', 'height']);
        });
    }
};
