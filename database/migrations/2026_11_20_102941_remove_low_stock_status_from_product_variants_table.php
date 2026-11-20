<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // "Low stock" is being dropped as a status — any variant currently
        // marked that way is still sellable, so it folds back into
        // "in_stock" rather than being left on a value the enum no longer
        // allows (which the enum->change() below would otherwise reject).
        DB::table('product_variants')->where('stock_status', 'low_stock')->update(['stock_status' => 'in_stock']);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->enum('stock_status', ['in_stock', 'out_of_stock'])->default('in_stock')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->enum('stock_status', ['in_stock', 'low_stock', 'out_of_stock'])->default('in_stock')->change();
        });
    }
};
