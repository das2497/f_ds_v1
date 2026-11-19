<?php

namespace App\Observers;

use App\Services\GoogleSheets\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

/**
 * Attached to both Category and Subcategory (see AppServiceProvider) —
 * either one changing can affect the Products sheet's Category/
 * Subcategory dropdown options, and the whole lookup tab is cheap enough
 * to just regenerate in full rather than track incremental changes.
 */
class CategorySheetObserver
{
    public function __construct(private readonly GoogleSheetsService $sheets) {}

    public function created(): void
    {
        $this->sync();
    }

    public function updated(): void
    {
        $this->sync();
    }

    public function deleted(): void
    {
        $this->sync();
    }

    private function sync(): void
    {
        if (! $this->sheets->configured()) {
            return;
        }

        try {
            $this->sheets->syncCategoryLookup();
        } catch (\Throwable $e) {
            Log::error('Google Sheets sync: failed to sync category lookup.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
