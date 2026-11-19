<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\GoogleSheets\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors every Product write to the Google Sheet. Runs after the DB
 * commit either way, so a Sheets outage never blocks saving a product;
 * failures are logged and swallowed.
 */
class ProductSheetObserver
{
    public function __construct(private readonly GoogleSheetsService $sheets) {}

    public function created(Product $product): void
    {
        $this->push($product);
    }

    public function updated(Product $product): void
    {
        $this->push($product);
    }

    public function deleted(Product $product): void
    {
        if (! $this->sheets->configured()) {
            return;
        }

        try {
            $this->sheets->deleteProduct($product->id);
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync: failed to delete product row.', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function push(Product $product): void
    {
        if (! $this->sheets->configured()) {
            return;
        }

        try {
            $this->sheets->upsertProduct($product);
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync: failed to push product row.', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
