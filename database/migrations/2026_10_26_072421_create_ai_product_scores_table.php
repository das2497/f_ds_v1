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
        Schema::create('ai_product_scores', function (Blueprint $table) {
            $table->id();
            $table->enum('vendor', ['printful', 'cj']);
            $table->string('vendor_external_id');
            $table->unsignedTinyInteger('score');
            $table->string('verdict');
            $table->json('reasons');
            $table->json('risks');
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->string('input_hash');
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->unique(['vendor', 'vendor_external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_product_scores');
    }
};
