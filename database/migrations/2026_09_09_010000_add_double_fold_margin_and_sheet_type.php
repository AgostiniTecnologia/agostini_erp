<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('fold_margin_double', 10, 3)->default(5)->after('fold_margin');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('cardboard_sheet_type', 10)->nullable()->after('cardboard_measurements');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cardboard_sheet_type');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('fold_margin_double');
        });
    }
};
