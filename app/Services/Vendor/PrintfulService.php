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
     * @param  array<int, array{vendor_sku: string, quantity: int}>  $items
     * @return array<string, mixed>
     */
    public function createOrder(string $internalReference, array $items, array $shippingAddress): array
    {
        $response = $this->client()->post('/orders', [
            'external_id' => $internalReference,
            'recipient' => $shippingAddress,
            'items' => collect($items)->map(fn ($item) => [
                'sync_variant_id' => $item['vendor_sku'],
                'quantity' => $item['quantity'],
            ])->all(),
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
