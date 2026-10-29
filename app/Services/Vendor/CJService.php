<?php

namespace App\Services\Vendor;

use App\Exceptions\VendorApiException;
use App\Models\AppSetting;
use App\Services\Html\DescriptionSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Wraps the CJ Dropshipping API (https://developers.cjdropshipping.com).
 *
 * The API Key is read from admin/settings-gateways.html (stored encrypted
 * in app_settings) first, falling back to CJ_API_KEY in .env. CJ's current
 * API2.0 auth takes a single API Key (generated from the CJ dashboard's
 * "API" app, Settings > API > Add API) — there is no email/password step.
 * Access tokens are cached (not stored in the database, per the
 * credential-service approach in 1_0.txt) and re-requested once they
 * expire.
 */
class CJService
{
    private const BASE_URL = 'https://developers.cjdropshipping.com/api2.0/v1';

    public function configured(): bool
    {
        return filled($this->apiKey());
    }

    private function apiKey(): ?string
    {
        return AppSetting::get('cj.api_key') ?: config('services.cj.api_key');
    }

    private const PER_PAGE = 20;

    /**
     * Uses CJ's current `listV2` search (Elasticsearch-backed `keyWord`
     * matching — the same engine behind CJ's own dashboard search). The
     * older `/product/list` + `productNameEn` combo this used to call is
     * deprecated and only does a weak per-word fuzzy match, which is why a
     * search like "rc car" used to come back with generic "car" products
     * instead of actual RC cars.
     *
     * CJ doesn't expose a product-count-by-category endpoint we can filter
     * `keyWord` results by server-side with a free-text category name, so
     * the `$category` filter is applied client-side against the category
     * names already included in each hit (requires `enable_category`).
     *
     * `$trendingOnly` maps to CJ's `productFlag=0` filter — "Trending
     * Products" as CJ's own catalog classifies them, not a local estimate.
     * `$sort === 'popularity'` maps to `orderBy=1`, CJ's "sort by listing
     * count" — the closest signal CJ's API exposes to a bestseller rank (no
     * dedicated bestseller/sales-volume endpoint exists as of their current
     * API2.0 docs).
     *
     * @return array{items: array<int, array<string, mixed>>, total_pages: int|null, total_records: int|null}
     */
    public function searchCatalog(?string $query = null, ?string $category = null, int $page = 1, ?string $sort = null, bool $trendingOnly = false): array
    {
        [$orderBy, $sortDir] = match ($sort) {
            'price_asc' => [2, 'asc'],
            'price_desc' => [2, 'desc'],
            'popularity' => [1, 'desc'],
            default => [0, 'desc'],
        };

        $params = [
            'page' => max(1, $page),
            'size' => self::PER_PAGE,
            'orderBy' => $orderBy,
            'sort' => $sortDir,
            'features' => ['enable_category'],
        ];

        if ($trendingOnly) {
            $params['productFlag'] = 0;
        }

        if (filled($query)) {
            $params['keyWord'] = $query;
        }

        $response = $this->client()->get('/product/listV2', $params);

        $items = collect($response->json('data.content', []))
            ->flatMap(fn ($group) => $group['productList'] ?? []);

        if (filled($category)) {
            $items = $items->filter(function ($item) use ($category) {
                $haystack = collect([
                    $item['oneCategoryName'] ?? null,
                    $item['twoCategoryName'] ?? null,
                    $item['threeCategoryName'] ?? null,
                ])->filter()->implode(' ');

                return str_contains(strtolower($haystack), strtolower($category));
            });
        }

        $mapped = $items->map(fn ($item) => [
            'vendor_external_id' => (string) ($item['id'] ?? ''),
            'title' => $item['nameEn'] ?? 'Untitled Product',
            'base_cost' => $this->parsePrice($item['sellPrice'] ?? null),
            'preview_image_url' => $this->firstImage($item['bigImage'] ?? null),
            'category_hint' => $item['threeCategoryName'] ?? $item['twoCategoryName'] ?? $item['oneCategoryName'] ?? null,
            'variant_count' => 0,
        ]);

        // CJ's `orderBy`/`sort` params are honored when browsing (no
        // `keyWord`), but silently ignored once a keyword search is active
        // — keyword results always come back relevance-ranked. Re-sort the
        // fetched page client-side so the "Sort by" control still does
        // something visible during a keyword search. Popularity has no
        // client-side equivalent (the listing-count value isn't in this
        // payload), so a keyword search with that sort just stays
        // relevance-ranked — only price sorting degrades gracefully here.
        if (filled($query) && in_array($sort, ['price_asc', 'price_desc'], true)) {
            $mapped = $sort === 'price_asc'
                ? $mapped->sortBy('base_cost')
                : $mapped->sortByDesc('base_cost');
        }

        return [
            'items' => $mapped->values()->all(),
            'total_pages' => $response->json('data.totalPages'),
            'total_records' => $response->json('data.totalRecords'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(string $externalId): array
    {
        $response = $this->client()->get('/product/query', ['pid' => $externalId]);
        $product = $response->json('data', []);
        $variants = collect($product['variants'] ?? []);

        $images = $this->productImages($product);
        $fallbackImage = $images[0] ?? null;
        $description = $this->parseDescription($product['description'] ?? null);
        $inventoryByVid = $this->fetchInventoryByVid($externalId);

        return [
            'vendor_external_id' => (string) ($product['pid'] ?? $externalId),
            'vendor_sku' => $product['productSku'] ?? null,
            'title' => $product['productNameEn'] ?? 'Untitled Product',
            'description' => $description['description_html'],
            'specifications' => $description['specs'],
            'preview_image_url' => $fallbackImage,
            'image_gallery' => $images,
            'category_hint' => $product['categoryName'] ?? null,
            'base_cost' => (float) ($variants->first()['variantSellPrice'] ?? $product['sellPrice'] ?? 0),
            'suggested_sell_price' => $product['suggestSellPrice'] ?? null,
            'listed_count' => isset($product['listedNum']) ? (int) $product['listedNum'] : null,
            'material' => $this->joinStringList($product['materialNameEnSet'] ?? null),
            'free_shipping' => (int) ($product['addMarkStatus'] ?? 0) === 1,
            'product_weight_lb' => isset($product['productWeight']) ? round(((float) $product['productWeight']) / 453.592, 2) : null,
            'supplier_name' => filled($product['supplierName'] ?? null) ? $product['supplierName'] : null,
            'variants' => $variants->map(function ($variant) use ($fallbackImage, $product, $inventoryByVid) {
                $options = $this->splitVariantOptions((string) ($variant['variantKey'] ?? ''), $product['productKeyEn'] ?? null);
                $stockQty = $this->variantInventory($variant, $inventoryByVid);

                return [
                    'vendor_sku' => (string) ($variant['variantSku'] ?? $variant['vid'] ?? ''),
                    'barcode' => $variant['barcode'] ?? null,
                    'option_label' => $variant['variantNameEn'] ?? trim(($variant['variantKey'] ?? '')),
                    'color' => $options['color'],
                    'size' => $options['size'],
                    'dimensions_in' => $this->variantDimensions($variant),
                    'cost_price' => (float) ($variant['variantSellPrice'] ?? 0),
                    'weight_lb' => isset($variant['variantWeight']) ? round(((float) $variant['variantWeight']) / 453.592, 2) : null,
                    'image' => $this->firstImage($variant['variantImage'] ?? null) ?? $fallbackImage,
                    'in_stock' => $stockQty > 0,
                    'stock_qty' => $stockQty,
                ];
            })->values()->all(),
        ];
    }

    /**
     * CJ's `/product/query` response carries an `inventories` field per
     * variant in its docs, but in practice it comes back `null` — stock has
     * to be fetched separately via `/product/stock/getInventoryByPid`,
     * which returns a `variantInventories` list keyed by `vid`. One extra
     * call per product-detail view; failures degrade to "unknown stock"
     * rather than breaking the page, since `/product/query` already
     * succeeded.
     *
     * @return array<string, int> vid => total inventory across warehouses
     */
    private function fetchInventoryByVid(string $pid): array
    {
        try {
            $response = $this->client()->get('/product/stock/getInventoryByPid', ['pid' => $pid]);
        } catch (\Throwable) {
            return [];
        }

        return collect($response->json('data.variantInventories', []))
            ->mapWithKeys(fn ($item) => [
                (string) ($item['vid'] ?? '') => (int) collect($item['inventory'] ?? [])
                    ->sum(fn ($inventory) => (int) ($inventory['totalInventory'] ?? 0)),
            ])
            ->all();
    }

    /**
     * CJ's main-image field has varied across accounts/API versions —
     * current docs name it `bigImage` (plus a `productImageSet` gallery),
     * while this integration has also seen it come back as `productImage`.
     * Accept all of them and merge into one deduped gallery, main image
     * first.
     *
     * @return array<int, string>
     */
    private function productImages(array $product): array
    {
        $gallery = collect();

        $main = $this->firstImage($product['bigImage'] ?? $product['productImage'] ?? null);

        if ($main) {
            $gallery->push($main);
        }

        if (is_array($product['productImageSet'] ?? null)) {
            $gallery = $gallery->merge($product['productImageSet']);
        }

        return $gallery->filter()->unique()->values()->all();
    }

    /**
     * CJ joins a variant's option values into `variantKey` (e.g.
     * "Black-XXL") and names the option types in the product's
     * `productKeyEn` (e.g. "Color-Size"), joined the same way. Zip them
     * back together so "Color"/"Size" can be shown as their own columns
     * instead of one opaque dash-joined string.
     *
     * @return array{color: ?string, size: ?string}
     */
    private function splitVariantOptions(string $variantKey, ?string $productKeyEn): array
    {
        $keys = filled($productKeyEn) ? array_map('trim', explode('-', $productKeyEn)) : [];
        $values = array_map('trim', explode('-', $variantKey));

        if ($keys === [] || count($keys) !== count($values)) {
            return ['color' => $variantKey !== '' ? $variantKey : null, 'size' => null];
        }

        $pairs = array_combine(array_map('strtolower', $keys), $values);

        return [
            'color' => $pairs['color'] ?? null,
            'size' => $pairs['size'] ?? null,
        ];
    }

    /**
     * Variant L/W/H come back in millimeters (consistent with
     * `variantVolume` = L * W * H in mm^3 in CJ's own example) — convert to
     * a human-readable inches string for display.
     */
    private function variantDimensions(array $variant): ?string
    {
        if (! isset($variant['variantLength'], $variant['variantWidth'], $variant['variantHeight'])) {
            return null;
        }

        $toInches = fn ($mm) => round(((float) $mm) / 25.4, 2);

        return $toInches($variant['variantLength']).' x '.$toInches($variant['variantWidth']).' x '.$toInches($variant['variantHeight']).' in';
    }

    private function joinStringList(mixed $list): ?string
    {
        if (! is_array($list)) {
            return null;
        }

        $joined = collect($list)->filter()->implode(', ');

        return $joined !== '' ? $joined : null;
    }

    /**
     * CJ's `/product/query` doesn't return a flat `variantStock` field (that
     * field doesn't exist in their API) — stock comes back nested as
     * `variants[].inventories[]`, one entry per warehouse/country, each
     * with its own `totalInventory`. Sum across every warehouse so a
     * variant counts as in stock if it's available anywhere.
     */
    /**
     * @param  array<string, int>  $inventoryByVid  from fetchInventoryByVid()
     */
    private function variantInventory(array $variant, array $inventoryByVid): int
    {
        $vid = (string) ($variant['vid'] ?? '');

        if (isset($inventoryByVid[$vid])) {
            return $inventoryByVid[$vid];
        }

        // Fallback for the rare case CJ does inline the field on this
        // endpoint (it's documented, just unreliable in practice).
        return (int) collect($variant['inventories'] ?? [])
            ->sum(fn ($inventory) => (int) ($inventory['totalInventory'] ?? 0));
    }

    /**
     * CJ's `productImage`/`variantImage` fields are sometimes a single URL
     * and sometimes a JSON-encoded array of URLs — normalize to one URL so
     * it fits the `preview_image_url` column instead of overflowing it.
     */
    private function firstImage(mixed $raw): ?string
    {
        if (is_array($raw)) {
            return $raw[0] ?? null;
        }

        if (is_string($raw) && str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded[0] ?? null;
            }
        }

        return filled($raw) ? $raw : null;
    }

    /**
     * CJ's `description` field is raw, loosely-formed HTML: a flat run of
     * `<p>` paragraphs repeating per-variant dimensions/weight/product code
     * under a literal "specifications" label (no real `<table>`), followed
     * by a "Description:" marker and the actual marketing copy. Rendered
     * as-is (escaped or not) it's unreadable, so split it into a structured
     * spec table and sanitized description HTML.
     *
     * @return array{specs: array<int, array{code: ?string, spec: ?string, dimensions: ?string, package_dimensions: ?string, weight_lb: ?string}>, description_html: ?string}
     */
    private function parseDescription(?string $raw): array
    {
        if (blank($raw)) {
            return ['specs' => [], 'description_html' => null];
        }

        $marker = stripos($raw, 'Description:');

        if ($marker === false) {
            return ['specs' => [], 'description_html' => DescriptionSanitizer::sanitize($raw)];
        }

        $specsHtml = substr($raw, 0, $marker);
        $descriptionHtml = substr($raw, $marker + strlen('Description:'));

        return [
            'specs' => $this->extractSpecRows($specsHtml),
            'description_html' => DescriptionSanitizer::sanitize($descriptionHtml),
        ];
    }

    /**
     * @return array<int, array{code: ?string, spec: ?string, dimensions: ?string, package_dimensions: ?string, weight_lb: ?string}>
     */
    private function extractSpecRows(string $html): array
    {
        $specs = [];

        foreach (preg_split('/<p>\s*specifications\s*<\/p>/i', $html) as $block) {
            if (! preg_match('/Product Code:\s*([A-Za-z0-9\-]+)\s*Specification:\s*([^<]+)/i', $block, $code)) {
                continue;
            }

            preg_match('/Length\s*\*\s*Width\s*\*\s*Height:\s*([^<]+?)\s*Length\s*\*\s*Width\s*\*\s*Height\s*\(Package\)/i', $block, $dimensions);
            preg_match('/\(Package\):\s*([^<]+)/i', $block, $packageDimensions);
            preg_match('/Weight:\s*([\d.]+\s*lb)\b/i', $block, $weight);

            $specs[] = [
                'code' => trim($code[1]),
                'spec' => trim($code[2]),
                'dimensions' => isset($dimensions[1]) ? trim($dimensions[1]) : null,
                'package_dimensions' => isset($packageDimensions[1]) ? trim($packageDimensions[1]) : null,
                'weight_lb' => isset($weight[1]) ? trim($weight[1]) : null,
            ];
        }

        return $specs;
    }

    /**
     * CJ's `sellPrice` is sometimes a plain number and sometimes a range
     * string like "1.90 -- 2.28" (when variants differ in price) — take the
     * lowest value so the research grid shows a real starting price.
     */
    private function parsePrice(mixed $raw): float
    {
        if (is_numeric($raw)) {
            return (float) $raw;
        }

        if (is_string($raw) && preg_match('/[\d.]+/', $raw, $matches)) {
            return (float) $matches[0];
        }

        return 0.0;
    }

    /**
     * CJ's order API doesn't support a client-supplied idempotency key the
     * way Printful does, so our own vendor_orders.status guard (never
     * re-submit a VendorOrder already marked "submitted") is the primary
     * duplicate-prevention mechanism for this vendor — this lookup by our
     * own internal_reference (stored as CJ's "remark"/order list filter)
     * is a best-effort secondary check once live credentials exist.
     *
     * @return array<string, mixed>|null
     */
    public function findOrderByExternalId(string $internalReference): ?array
    {
        try {
            $response = $this->client()->get('/shopping/order/list', ['orderNum' => $internalReference]);
        } catch (\Throwable) {
            return null;
        }

        return $response->json('data.list.0');
    }

    /**
     * @param  array<int, array{vendor_sku: string, quantity: int}>  $items
     * @return array<string, mixed>
     */
    public function createOrder(string $internalReference, array $items, array $shippingAddress): array
    {
        $response = $this->client()->post('/shopping/order/createOrder', [
            'orderNum' => $internalReference,
            'shippingAddress' => $shippingAddress,
            'products' => collect($items)->map(fn ($item) => [
                'vid' => $item['vendor_sku'],
                'quantity' => $item['quantity'],
            ])->all(),
        ]);

        return $response->json('data', []);
    }

    private function client()
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['CJ-Access-Token' => $this->accessToken()])
            ->timeout(15)
            ->throw();
    }

    private function accessToken(): string
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('CJ Dropshipping');
        }

        return Cache::remember('cj_access_token', now()->addDays(14), function () {
            $response = Http::baseUrl(self::BASE_URL)
                ->timeout(15)
                ->throw()
                ->post('/authentication/getAccessToken', [
                    'apiKey' => $this->apiKey(),
                ]);

            return $response->json('data.accessToken');
        });
    }
}
