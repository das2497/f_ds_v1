<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorProductCandidate;
use App\Services\Html\DescriptionSanitizer;
use App\Services\Images\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Receives a one-click product scrape from the AliExpress Chrome
 * extension. Unlike the Printful/CJ research flow (search → select →
 * admin fills in a form → publish), there's no admin review step here:
 * the product is created immediately as a real Product + ProductVariant
 * set, just always `is_active = false`, so it shows up for review in the
 * admin panel and the synced Sheet without ever being visible to shoppers
 * until someone deliberately activates it.
 */
class AliExpressImportController extends Controller
{
    public function __construct(private readonly ImageOptimizer $imageOptimizer) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_external_id' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'preview_image_url' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'category_hint' => ['nullable', 'string', 'max:255'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.vendor_sku' => ['nullable', 'string', 'max:100'],
            'variants.*.option_label' => ['required', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.size' => ['nullable', 'string', 'max:100'],
            'variants.*.weight_lb' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['required', 'numeric', 'min:0'],
            'variants.*.image' => ['nullable', 'string'],
        ]);

        $candidate = VendorProductCandidate::updateOrCreate(
            ['vendor' => 'aliexpress', 'vendor_external_id' => $validated['vendor_external_id']],
            [
                'raw_payload' => $validated,
                'title' => $validated['title'],
                'base_cost' => collect($validated['variants'])->min('cost_price'),
                'preview_image_url' => $validated['preview_image_url'] ?? ($validated['images'][0] ?? null),
                'category_hint' => $validated['category_hint'] ?? null,
                'variant_count' => count($validated['variants']),
                'status' => 'selected',
                'selected_at' => now(),
            ]
        );

        $existing = Product::where('vendor_product_candidate_id', $candidate->id)->first();

        if ($existing) {
            return response()->json(['status' => 'exists', 'product_id' => $existing->id]);
        }

        $product = DB::transaction(function () use ($validated, $candidate) {
            $imageUrls = collect($validated['images'] ?? [$validated['preview_image_url'] ?? null])
                ->filter()
                ->unique()
                ->map(fn ($url) => $this->imageOptimizer->storeFromUrlAsPublicUrl($url, 'products'))
                ->filter()
                ->values()
                ->all();

            $product = Product::create([
                'vendor_product_candidate_id' => $candidate->id,
                'source_vendor' => 'aliexpress',
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title']),
                'description' => DescriptionSanitizer::sanitize($validated['description'] ?? null),
                'price' => collect($validated['variants'])->min('cost_price'),
                'images' => $imageUrls,
                'is_active' => false,
            ]);

            foreach ($validated['variants'] as $i => $variant) {
                $product->variants()->create([
                    'sku' => $this->generateSku($candidate->vendor_external_id, $i),
                    'vendor_sku' => $variant['vendor_sku'] ?? null,
                    'option_label' => $variant['option_label'],
                    'image' => $this->imageOptimizer->storeFromUrlAsPublicUrl($variant['image'] ?? null, 'products/variants'),
                    'color' => $variant['color'] ?? null,
                    'size' => $variant['size'] ?? null,
                    'weight_lb' => $variant['weight_lb'] ?? null,
                    'cost_price' => $variant['cost_price'],
                    'stock_status' => 'in_stock',
                    'is_active' => true,
                ]);
            }

            $candidate->update(['status' => 'listed']);

            return $product;
        });

        AuditLog::record('Product Imported from AliExpress', "\"{$product->title}\" (#{$product->id}) — inactive, pending review.");

        return response()->json(['status' => 'created', 'product_id' => $product->id], 201);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$i;
        }

        return $slug;
    }

    private function generateSku(string $vendorExternalId, int $index): string
    {
        $base = 'ALI-'.preg_replace('/[^A-Za-z0-9]/', '', $vendorExternalId).'-'.($index + 1);
        $sku = $base;
        $i = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = "{$base}-".++$i;
        }

        return $sku;
    }
}
