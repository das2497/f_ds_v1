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
        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->dropColumn('media_scale');
            $table->unsignedSmallInteger('media_width')->default(300)->after('background_color');
            $table->unsignedSmallInteger('media_height')->default(300)->after('media_width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->dropColumn(['media_width', 'media_height']);
            $table->unsignedTinyInteger('media_scale')->default(90);
        });
    }
};
