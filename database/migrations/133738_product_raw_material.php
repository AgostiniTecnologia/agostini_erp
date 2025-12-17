<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_raw_material', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('raw_material_id');
            $table->decimal('quantity', 15, 3)->default(1);
            $table->string('unit_of_measure')->default('unidade');
            $table->timestamps();

            $table->primary(['product_id', 'raw_material_id']);

            $table->foreign('product_id')->references('uuid')->on('products')->onDelete('cascade');
            $table->foreign('raw_material_id')->references('uuid')->on('raw_materials')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_raw_material');
    }
};
