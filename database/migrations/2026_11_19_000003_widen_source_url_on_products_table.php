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
        // A real AliExpress product URL carries a tracking query string
        // (algo_pvid, pdp_npi, utparam-url, ...) that's routinely several
        // hundred characters — the extension now saves a clean canonical
        // link instead, but an admin can still paste a messy one by hand
        // into the manual-product form, so the column needs to actually
        // hold it rather than truncate/error.
        Schema::table('products', function (Blueprint $table) {
            $table->text('source_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source_url')->nullable()->change();
        });
    }
};
