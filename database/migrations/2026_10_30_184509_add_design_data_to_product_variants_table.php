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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('design_data')->nullable()->after('image');
        });

        // Carry forward any product that already has a design saved under
        // the old product-level shape (design_data lived on `products`
        // before this was rescoped to per-variant) onto that product's
        // first variant — the old data predates variants having designs of
        // their own, so there's no per-variant assignment to preserve.
        DB::table('products')->whereNotNull('design_data')->get(['id', 'design_data'])->each(function ($product) {
            $firstVariantId = DB::table('product_variants')
                ->where('product_id', $product->id)
                ->orderBy('id')
                ->value('id');

            if ($firstVariantId) {
                DB::table('product_variants')->where('id', $firstVariantId)->update([
                    'design_data' => $product->design_data,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('design_data');
        });
    }
};
