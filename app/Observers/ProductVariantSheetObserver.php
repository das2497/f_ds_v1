<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\GoogleSheets\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors every ProductVariant write to the Google Sheet. See
 * ProductSheetObserver for why failures are logged, not thrown.
 */
class ProductVariantSheetObserver
{
    public function __construct(private readonly GoogleSheetsService $sheets) {}

    public function created(ProductVariant $variant): void
    {
        $this->push($variant);
    }

    public function updated(ProductVariant $variant): void
    {
        $this->push($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        if (! $this->sheets->configured()) {
            return;
        }

        try {
            $this->sheets->deleteVariant($variant->id);
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync: failed to delete variant row.', [
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function push(ProductVariant $variant): void
    {
        if (! $this->sheets->configured()) {
            return;
        }

        try {
            $this->sheets->upsertVariant($variant);
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync: failed to push variant row.', [
                'variant_id' => $variant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
