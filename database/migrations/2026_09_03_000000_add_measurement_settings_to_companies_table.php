<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('length_unit', 10)->default('m')->after('operational_profile');
            $table->string('weight_unit', 10)->default('kg')->after('length_unit');
            $table->decimal('fold_margin', 10, 3)->default(5)->after('weight_unit');
            $table->decimal('length_flap_default', 10, 3)->default(60)->after('fold_margin');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['length_unit', 'weight_unit', 'fold_margin', 'length_flap_default']);
        });
    }
};
