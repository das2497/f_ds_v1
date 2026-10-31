<?php

namespace App\Services\Vendor;

use App\Exceptions\VendorApiException;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * Wraps the Printful Catalog API (https://api.printful.com).
 *
 * The API key is read from admin/settings-gateways.html (stored encrypted
 * in app_settings) first, falling back to PRINTFUL_API_KEY in .env. Field
 * names below follow Printful's documented v1 Catalog API; re-verify
 * against a live response once real credentials are available.
 */
class PrintfulService
{
    private const BASE_URL = 'https://api.printful.com';

    /**
     * Used when `/mockup-generator/printfiles` can't be reached (vendor not
     * configured, API error) so the design canvas still has a sane size to
     * work with instead of failing outright.
     */
    private const DEFAULT_PLACEMENTS = [
        'front' => ['width' => 1800, 'height' => 2400, 'dpi' => 300],
    ];

    /**
     * This app's own placement names → the `type` key Printful's order
     * `files` entries expect. `front` is Printful's primary/"default" print
     * area; everything else (currently just `back`) passes through as-is,
     * which covers most apparel product types — a handful of product types
     * use different placement keys Printful-side.
     */
    private const PLACEMENT_FILE_TYPES = ['front' => 'default', 'back' => 'back'];

    public function configured(): bool
    {
        return filled($this->apiKey());
    }

    private function apiKey(): ?string
    {
        return AppSetting::get('printful.api_key') ?: config('services.printful.api_key');
    }

    private const PER_PAGE = 20;

    /**
     * Printful's `/products` endpoint returns its whole catalog in one
     * shot (no server-side keyword search), so `$query`/`$category` are
     * matched client-side and the catalog is paginated in memory to keep
     * the same shape the view expects from CJService.
     *
     * `$trendingOnly` is accepted only to keep this signature interchangeable
     * with CJService (the research UI calls whichever vendor's service via
     * a shared `PrintfulService|CJService` type) — Printful's catalog is
     * print-on-demand blanks, not a marketplace with a "trending" concept,
     * so it's ignored here.
     *
     * @return array{items: array<int, array<string, mixed>>, total_pages: int|null, total_records: int|null}
     */
    public function searchCatalog(?string $query = null, ?string $category = null, int $page = 1, ?string $sort = null, bool $trendingOnly = false): array
    {
        $response = $this->client()->get('/products');

        $items = collect($response->json('result', []));

        if (filled($query)) {
            $items = $items->filter(fn ($item) => str_contains(
                strtolower($item['title'] ?? ''),
                strtolower($query)
            ));
        }

        if (filled($category)) {
            $items = $items->filter(fn ($item) => str_contains(
                strtolower($item['type_name'] ?? ''),
                strtolower($category)
            ));
        }

        $items = $items->values();
        $totalRecords = $items->count();

        return [
            'items' => $items->slice((max(1, $page) - 1) * self::PER_PAGE, self::PER_PAGE)
                ->map(fn ($item) => [
                    'vendor_external_id' => (string) $item['id'],
                    'title' => $item['title'] ?? 'Untitled Product',
                    'preview_image_url' => $item['image'] ?? null,
                    'category_hint' => $item['type_name'] ?? null,
                    'variant_count' => $item['variant_count'] ?? 0,
                ])->values()->all(),
            'total_pages' => $totalRecords > 0 ? (int) ceil($totalRecords / self::PER_PAGE) : 1,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(string $externalId): array
    {
        $response = $this->client()->get("/products/{$externalId}");
        $result = $response->json('result', []);

        $product = $result['product'] ?? [];
        $variants = collect($result['variants'] ?? []);

        return [
            'vendor_external_id' => (string) ($product['id'] ?? $externalId),
            'title' => $product['title'] ?? 'Untitled Product',
            'description' => $product['description'] ?? null,
            'preview_image_url' => $product['image'] ?? null,
            'category_hint' => $product['type_name'] ?? null,
            'base_cost' => (float) ($variants->first()['price'] ?? 0),
            'variants' => $variants->map(fn ($variant) => [
                'vendor_sku' => (string) ($variant['sku'] ?? $variant['id']),
                'option_label' => trim(($variant['color'] ?? '').' / '.($variant['size'] ?? ''), ' /'),
                'color' => $variant['color'] ?? null,
                'size' => $variant['size'] ?? null,
                'cost_price' => (float) ($variant['price'] ?? 0),
                'weight_lb' => isset($variant['weight']) ? round(((float) $variant['weight']) / 453.592, 2) : null,
                'image' => $variant['image'] ?? ($product['image'] ?? null),
                'in_stock' => (bool) ($variant['in_stock'] ?? true),
            ])->values()->all(),
        ];
    }

    /**
     * The real print-area pixel size per placement (front/back/...), so the
     * admin design canvas matches what Printful will actually print instead
     * of a generic guess. All variants of a given catalog product share the
     * same printfile layout, so the first `variant_printfiles` entry is
     * representative of the whole product.
     *
     * @return array<string, array{width: int, height: int, dpi: int}>
     */
    public function getPrintfileTemplate(string $externalId): array
    {
        try {
            $response = $this->client()->get("/mockup-generator/printfiles/{$externalId}");
        } catch (\Throwable) {
            return self::DEFAULT_PLACEMENTS;
        }

        $result = $response->json('result', []);
        $placements = array_keys($result['available_placements'] ?? []);
        $printfiles = collect($result['printfiles'] ?? [])->keyBy('printfile_id');
        $variantPlacements = $result['variant_printfiles'][0]['placements'] ?? [];

        if ($placements === [] || $variantPlacements === []) {
            return self::DEFAULT_PLACEMENTS;
        }

        $templates = [];

        foreach ($placements as $placement) {
            $printfile = $printfiles->get($variantPlacements[$placement] ?? null);

            if (! $printfile) {
                continue;
            }

            $templates[$placement] = [
                'width' => (int) $printfile['width'],
                'height' => (int) $printfile['height'],
                'dpi' => (int) ($printfile['dpi'] ?? 300),
            ];
        }

        return $templates !== [] ? $templates : self::DEFAULT_PLACEMENTS;
    }

    /**
     * Maps this app's `design_data` (keyed by our own placement names) to
     * the `files` array Printful's order items expect. Pure/static-shaped
     * on purpose — no HTTP call — so fulfillment can attach a listing's
     * saved artwork to an order without needing a live API round trip.
     *
     * @param  array<string, array{print_file_url?: string|null}>  $designData
     * @return array<int, array{type: string, url: string}>
     */
    public function buildOrderFiles(array $designData): array
    {
        $files = [];

        foreach ($designData as $placement => $data) {
            $url = $data['print_file_url'] ?? null;

            if (blank($url)) {
                continue;
            }

            $files[] = [
                'type' => self::PLACEMENT_FILE_TYPES[$placement] ?? $placement,
                'url' => $url,
            ];
        }

        return $files;
    }

    /**
     * Idempotent pre-check: Printful orders are created with our internal
     * reference as their `external_id`, so this looks the order up by that
     * value before ever creating a new one — the core duplicate-submission
     * guard required by 1_0.txt §5.
     *
     * @return array<string, mixed>|null
     */
    public function findOrderByExternalId(string $internalReference): ?array
    {
        try {
            $response = $this->client()->get("/orders/@{$internalReference}");
        } catch (\Throwable) {
            return null;
        }

        return $response->json('result');
    }

    /**
     * Items carry a catalog `variant_id` (via `vendor_sku` — this app only
     * ever fetches Printful's Catalog API, never creates Store/Sync
     * products), plus an optional `files` array with any artwork saved
     * against the listing. Printful prints straight onto the catalog blank
     * using those files — no Sync Product step needed.
     *
     * @param  array<int, array{vendor_sku: string, quantity: int, files?: array<int, array{type: string, url: string}>}>  $items
     * @return array<string, mixed>
     */
    public function createOrder(string $internalReference, array $items, array $shippingAddress): array
    {
        $response = $this->client()->post('/orders', [
            'external_id' => $internalReference,
            'recipient' => $shippingAddress,
            'items' => collect($items)->map(fn ($item) => array_filter([
                'variant_id' => $item['vendor_sku'],
                'quantity' => $item['quantity'],
                'files' => $item['files'] ?? [],
            ], fn ($value, $key) => $key !== 'files' || $value !== [], ARRAY_FILTER_USE_BOTH))->all(),
        ]);

        return $response->json('result', []);
    }

    private function client()
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('Printful');
        }

        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->apiKey())
            ->timeout(15)
            ->throw();
    }
}
