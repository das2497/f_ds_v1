<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\GoogleSheets\GoogleSheetsService;
use Illuminate\Console\Command;

/**
 * The DB→Sheet observers only fire on create/update/delete, so a product
 * that existed before Google Sheets sync was configured — or that nobody
 * has touched since — never makes it into the Sheet on its own. Run this
 * once after configuring Sheets sync (or anytime afterwards, it's
 * idempotent) to push every current product/variant in.
 */
class BackfillGoogleSheets extends Command
{
    protected $signature = 'sheets:backfill';

    protected $description = 'Push every existing product and variant into the configured Google Sheet.';

    public function handle(GoogleSheetsService $sheets): int
    {
        if (! $sheets->configured()) {
            $this->error('Google Sheets isn\'t configured yet — set it up under Super Admin > Gateway Settings first.');

            return self::FAILURE;
        }

        // Each chunk goes out as one or two batched write calls (see
        // GoogleSheetsService::bulkUpsert*()) instead of one API call per
        // row — a one-call-per-row backfill blows through Google's
        // per-minute Sheets API quota the moment the catalog has more
        // than a few dozen products.
        $productCount = 0;
        Product::query()->with(['category', 'subcategory'])->orderBy('id')->chunk(200, function ($products) use ($sheets, &$productCount) {
            $sheets->bulkUpsertProducts($products);
            $productCount += $products->count();
        });

        $variantCount = 0;
        ProductVariant::query()->orderBy('id')->chunk(200, function ($variants) use ($sheets, &$variantCount) {
            $sheets->bulkUpsertVariants($variants);
            $variantCount += $variants->count();
        });

        $this->info("Pushed {$productCount} products and {$variantCount} variants to the Sheet.");

        return self::SUCCESS;
    }
}
