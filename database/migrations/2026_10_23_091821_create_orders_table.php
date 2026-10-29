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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('shipping_name');
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_state', 2);
            $table->string('shipping_zip', 10);
            $table->string('shipping_country', 2)->default('US');
            $table->string('shipping_method')->default('standard');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_total', 10, 2);
            $table->decimal('tax_total', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending_payment');
            $table->json('vendor_split_tax_breakdown')->nullable();
            $table->timestamp('release_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
