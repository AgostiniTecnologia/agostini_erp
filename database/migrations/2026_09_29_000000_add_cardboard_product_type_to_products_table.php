<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Nulo mantém produtos existentes compatíveis com a tela de caixa.
            $table->string('cardboard_product_type', 20)->nullable()->after('cardboard_measurements');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('cardboard_product_type');
        });
    }
};
