<?php

namespace App\Services\GoogleSheets;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pushes Product/ProductVariant rows to a Google Sheet (one tab per model)
 * via the raw Sheets v4 REST API. google/apiclient is only used for the
 * service-account JWT exchange — the generated google/apiclient-services
 * classes are never touched, so this stays lightweight and testable with
 * Http::fake() like the other Vendor services.
 */
class GoogleSheetsService
{
    public const PRODUCTS_SHEET = 'Products';

    public const VARIANTS_SHEET = 'Variants';

    public const PRODUCT_HEADERS = ['ID', 'Title', 'Description', 'Price', 'Compare At Price', 'Active', 'Source Vendor', 'Updated At', 'Sync Status'];

    public const VARIANT_HEADERS = ['ID', 'Product ID', 'SKU', 'Option Label', 'Color', 'Size', 'Cost Price', 'Price', 'Stock Status', 'Active', 'Updated At', 'Sync Status'];

    private const BASE_URL = 'https://sheets.googleapis.com/v4/spreadsheets';

    /** @var array<int, array{sheetId: int, title: string}>|null */
    private ?array $sheetMeta = null;

    public function configured(): bool
    {
        return filled($this->spreadsheetId()) && filled($this->credentialsConfig());
    }

    public function upsertProduct(Product $product): void
    {
        $this->upsertRow(self::PRODUCTS_SHEET, self::PRODUCT_HEADERS, $product->id, [
            'ID' => $product->id,
            'Title' => $product->title,
            'Description' => trim(strip_tags((string) $product->description)),
            'Price' => (float) $product->price,
            'Compare At Price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : '',
            'Active' => $product->is_active ? 'TRUE' : 'FALSE',
            'Source Vendor' => (string) $product->source_vendor,
            'Updated At' => optional($product->updated_at)->toDateTimeString(),
            'Sync Status' => '',
        ]);
    }

    public function deleteProduct(int $productId): void
    {
        $this->deleteRow(self::PRODUCTS_SHEET, $productId);
    }

    public function upsertVariant(ProductVariant $variant): void
    {
        $this->upsertRow(self::VARIANTS_SHEET, self::VARIANT_HEADERS, $variant->id, [
            'ID' => $variant->id,
            'Product ID' => $variant->product_id,
            'SKU' => $variant->sku,
            'Option Label' => $variant->option_label,
            'Color' => (string) $variant->color,
            'Size' => (string) $variant->size,
            'Cost Price' => (float) $variant->cost_price,
            'Price' => $variant->price !== null ? (float) $variant->price : '',
            'Stock Status' => $variant->stock_status,
            'Active' => $variant->is_active ? 'TRUE' : 'FALSE',
            'Updated At' => optional($variant->updated_at)->toDateTimeString(),
            'Sync Status' => '',
        ]);
    }

    public function deleteVariant(int $variantId): void
    {
        $this->deleteRow(self::VARIANTS_SHEET, $variantId);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $values  keyed by header label
     */
    private function upsertRow(string $sheet, array $headers, int $id, array $values): void
    {
        $this->ensureSheet($sheet, $headers);

        $ordered = array_map(fn ($header) => $values[$header] ?? '', $headers);
        $row = $this->findRow($sheet, $id);

        if ($row !== null) {
            $this->http()->put($this->valuesUrl("{$sheet}!A{$row}"), ['values' => [$ordered]]);

            return;
        }

        $this->http()->post($this->valuesUrl("{$sheet}!A1", append: true), ['values' => [$ordered]]);
    }

    private function deleteRow(string $sheet, int $id): void
    {
        $row = $this->findRow($sheet, $id);
        $sheetId = $this->sheetId($sheet);

        if ($row === null || $sheetId === null) {
            return;
        }

        $this->http()->post($this->url(':batchUpdate'), [
            'requests' => [[
                'deleteDimension' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'ROWS',
                        'startIndex' => $row - 1,
                        'endIndex' => $row,
                    ],
                ],
            ]],
        ]);
    }

    /**
     * Column A holds the row's DB id — read the whole column and match on
     * it, rather than trusting row position, since rows get sorted or
     * reordered by hand in the sheet.
     */
    private function findRow(string $sheet, int $id): ?int
    {
        $column = $this->http()->get($this->valuesUrl("{$sheet}!A:A"))->json('values', []);

        foreach ($column as $index => $cell) {
            if ((string) ($cell[0] ?? '') === (string) $id) {
                return $index + 1;
            }
        }

        return null;
    }

    private function sheetId(string $title): ?int
    {
        foreach ($this->metadata() as $sheet) {
            if ($sheet['title'] === $title) {
                return $sheet['sheetId'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function ensureSheet(string $sheet, array $headers): void
    {
        if ($this->sheetId($sheet) === null) {
            $this->http()->post($this->url(':batchUpdate'), [
                'requests' => [['addSheet' => ['properties' => ['title' => $sheet]]]],
            ]);
            $this->sheetMeta = null;
        }

        $headerRow = $this->http()->get($this->valuesUrl("{$sheet}!1:1"))->json('values.0', []);

        if ($headerRow !== $headers) {
            $this->http()->put($this->valuesUrl("{$sheet}!A1"), ['values' => [$headers]]);
        }
    }

    /**
     * @return array<int, array{sheetId: int, title: string}>
     */
    private function metadata(): array
    {
        if ($this->sheetMeta !== null) {
            return $this->sheetMeta;
        }

        $sheets = $this->http()->get($this->url(), ['fields' => 'sheets.properties'])->json('sheets', []);

        return $this->sheetMeta = collect($sheets)->map(fn ($sheet) => [
            'sheetId' => $sheet['properties']['sheetId'],
            'title' => $sheet['properties']['title'],
        ])->all();
    }

    private function http()
    {
        return Http::withToken($this->accessToken())->timeout(15)->throw();
    }

    private function url(string $path = ''): string
    {
        return self::BASE_URL.'/'.$this->spreadsheetId().$path;
    }

    private function valuesUrl(string $range, bool $append = false): string
    {
        $url = $this->url('/values/'.rawurlencode($range)).($append ? ':append' : '');
        $query = $append ? 'valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS' : 'valueInputOption=USER_ENTERED';

        return "{$url}?{$query}";
    }

    private function accessToken(): string
    {
        return Cache::remember('google_sheets_access_token', 3000, function () {
            $client = new GoogleClient;
            $client->setAuthConfig($this->credentialsConfig());
            $client->addScope('https://www.googleapis.com/auth/spreadsheets');

            return $client->fetchAccessTokenWithAssertion()['access_token'];
        });
    }

    private function spreadsheetId(): ?string
    {
        return AppSetting::get('google_sheets.spreadsheet_id') ?: config('services.google_sheets.spreadsheet_id');
    }

    /**
     * The admin panel (Super Admin > Gateway Settings) lets an admin paste
     * the service account's JSON key directly — that always wins. Falling
     * back to a file on disk (GOOGLE_SHEETS_CREDENTIALS_PATH) only matters
     * for a server set up before that panel existed, or one provisioned
     * straight from .env instead.
     *
     * @return array<string, mixed>|string|null what google/apiclient's
     *                                          Client::setAuthConfig() accepts — a decoded JSON array or a
     *                                          path to the key file.
     */
    private function credentialsConfig(): array|string|null
    {
        $json = AppSetting::get('google_sheets.credentials_json');

        if (filled($json)) {
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : null;
        }

        $path = config('services.google_sheets.credentials_path');

        return $path && is_file(base_path($path)) ? base_path($path) : null;
    }
}
