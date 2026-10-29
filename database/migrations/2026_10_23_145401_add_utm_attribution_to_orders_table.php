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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('vendor_split_tax_breakdown');
            $table->string('first_touch_source')->nullable()->after('session_id');
            $table->string('first_touch_medium')->nullable()->after('first_touch_source');
            $table->string('first_touch_campaign')->nullable()->after('first_touch_medium');
            $table->string('first_touch_ad_id')->nullable()->after('first_touch_campaign');
            $table->string('last_touch_source')->nullable()->after('first_touch_ad_id');
            $table->string('last_touch_medium')->nullable()->after('last_touch_source');
            $table->string('last_touch_campaign')->nullable()->after('last_touch_medium');
            $table->string('last_touch_ad_id')->nullable()->after('last_touch_campaign');
            // Only captured when the customer has granted analytics consent
            // (1_0.txt §7) — used for Meta/TikTok server-side match quality.
            $table->string('fbp')->nullable()->after('last_touch_ad_id');
            $table->string('fbc')->nullable()->after('fbp');
            $table->string('ttclid')->nullable()->after('fbc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'session_id',
                'first_touch_source', 'first_touch_medium', 'first_touch_campaign', 'first_touch_ad_id',
                'last_touch_source', 'last_touch_medium', 'last_touch_campaign', 'last_touch_ad_id',
                'fbp', 'fbc', 'ttclid',
            ]);
        });
    }
};
