<?php

namespace App\Services\GoogleSheets;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pushes Product/ProductVariant rows to a Google Sheet (one tab per model)
 * via the raw Sheets v4 REST API. The service-account JWT bearer token
 * (RFC 7523) is signed by hand with PHP's own openssl extension instead
 * of pulling in google/apiclient: that package drags in
 * google/apiclient-services, a ~38,000-file generated-code package that
 * blew past this app's shared-hosting composer install limits on deploy.
 * Plain PHP + the Http facade keeps this testable with Http::fake() like
 * the other Vendor services, with zero extra dependencies.
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
            $credentials = $this->credentialsConfig();

            if ($credentials === null) {
                throw new RuntimeException('Google Sheets credentials are not configured.');
            }

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedJwt($credentials),
            ])->throw();

            return $response->json('access_token');
        });
    }

    /**
     * Hand-signs the service-account JWT bearer assertion Google's token
     * endpoint expects (RFC 7523) — see the class docblock for why this
     * isn't done via google/apiclient.
     *
     * @param  array<string, mixed>  $credentials  the decoded service account JSON
     */
    private function signedJwt(array $credentials): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Google Sheets service account private key is invalid.');
        }

        openssl_sign("{$header}.{$claims}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$claims}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function spreadsheetId(): ?string
    {
        return AppSetting::get('google_sheets.spreadsheet_id') ?: config('services.google_sheets.spreadsheet_id');
    }

    /**
     * The admin panel (Super Admin > Gateway Settings) lets an admin paste
     * the service account's JSON key directly — that always wins. Falling
     * back to a file on disk (GOOGLE_SHEETS_CREDENTIALS_PATH) only matters
     * for a server provisioned straight from .env instead of the panel.
     *
     * @return array<string, mixed>|null the decoded service account JSON
     */
    private function credentialsConfig(): ?array
    {
        $json = AppSetting::get('google_sheets.credentials_json');

        if (blank($json)) {
            $path = config('services.google_sheets.credentials_path');
            $json = $path && is_file(base_path($path)) ? file_get_contents(base_path($path)) : null;
        }

        if (blank($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && filled($decoded['private_key'] ?? null) && filled($decoded['client_email'] ?? null)
            ? $decoded
            : null;
    }
}
