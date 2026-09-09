<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('fold_margin', 10, 3)->nullable()->after('cardboard_measurements');
            $table->decimal('length_flap_default', 10, 3)->nullable()->after('fold_margin');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['fold_margin', 'length_flap_default']);
        });
    }
};
