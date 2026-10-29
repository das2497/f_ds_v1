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
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('event_name', [
                'page_view', 'view_item', 'search', 'add_to_cart', 'begin_checkout', 'purchase', 'refund',
            ]);
            $table->string('event_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_ad_id')->nullable();
            $table->string('path')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('value', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('ga4_sent_at')->nullable();
            $table->timestamp('meta_sent_at')->nullable();
            $table->timestamp('tiktok_sent_at')->nullable();
            $table->timestamps();

            $table->index(['event_name', 'created_at']);
            $table->index(['utm_source', 'utm_medium', 'utm_campaign']);
            $table->unique(['event_name', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
