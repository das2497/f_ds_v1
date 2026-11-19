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
            $table->enum('vendor', ['printful', 'cj', 'aliexpress'])->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->enum('source_vendor', ['printful', 'cj', 'manual', 'aliexpress'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_product_candidates', function (Blueprint $table) {
            $table->enum('vendor', ['printful', 'cj'])->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->enum('source_vendor', ['printful', 'cj', 'manual'])->nullable()->change();
        });
    }
};
