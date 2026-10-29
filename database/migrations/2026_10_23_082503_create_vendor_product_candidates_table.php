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
        Schema::create('vendor_product_candidates', function (Blueprint $table) {
            $table->id();
            $table->enum('vendor', ['printful', 'cj']);
            $table->string('vendor_external_id');
            $table->json('raw_payload');
            $table->string('title');
            $table->decimal('base_cost', 10, 2);
            $table->string('preview_image_url')->nullable();
            $table->string('category_hint')->nullable();
            $table->unsignedInteger('variant_count')->default(0);
            $table->enum('status', ['researched', 'selected', 'listed', 'discarded'])->default('researched');
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor', 'vendor_external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_product_candidates');
    }
};
