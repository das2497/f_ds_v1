<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_product_candidates', function (Blueprint $table) {
            $table->json('design_data')->nullable()->after('raw_payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_product_candidates', function (Blueprint $table) {
            $table->dropColumn('design_data');
        });
    }
};
