<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorProductCandidate;
use App\Services\Html\DescriptionSanitizer;
use App\Services\Vendor\PrintfulService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const MAX_VIDEO_KB = 20480;

    public function __construct(private readonly PrintfulService $printful) {}

    public function index(Request $request): View
    {
        $products = Product::with(['variants'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('origin'), fn ($q) => $q->where('source_vendor', $request->string('origin')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create(VendorProductCandidate $candidate): View
    {
        $categories = Category::with('subcategories')->orderBy('sort_order')->get();
        $categoryMatch = $this->matchCategory($candidate->category_hint, $categories);

        // Re-sanitize even though CJService already does this on fetch: a
        // candidate selected before that fix was added still has the old,
        // unsanitized HTML sitting in its raw_payload snapshot. Cheap and
        // idempotent on already-clean HTML.
        $suggestedDescription = DescriptionSanitizer::sanitize($candidate->raw_payload['description'] ?? null);

        return view('admin.products.create', [
            'candidate' => $candidate,
            'categories' => $categories,
            'suggestedCategoryId' => $categoryMatch['category_id'],
            'suggestedSubcategoryId' => $categoryMatch['subcategory_id'],
            'suggestedDescription' => $suggestedDescription,
            'designPlacements' => $candidate->vendor === 'printful'
                ? $this->printful->getPrintfileTemplate($candidate->vendor_external_id)
                : [],
        ]);
    }

    /**
     * Vendors describe products in their own taxonomy (e.g. CJ's
     * "Home & Garden, Furniture / Home Storage / Home Office Storage"),
     * which never matches this shop's fixed category list verbatim. Guess
     * the closest category/subcategory by overlapping significant words,
     * so the form starts pre-selected instead of forcing the admin to
     * search a 7-category dropdown from scratch; they can always change it.
     *
     * @return array{category_id: ?int, subcategory_id: ?int}
     */
    private function matchCategory(?string $hint, Collection $categories): array
    {
        $hintWords = $this->significantWords((string) $hint);

        if ($hintWords === []) {
            return ['category_id' => null, 'subcategory_id' => null];
        }

        // Score subcategories first and prefer them: a vendor's leaf
        // category name (e.g. "Outdoor Play Equipment") is far more likely
        // to overlap with a specific subcategory ("Outdoor Play") than with
        // this shop's broad top-level name ("Toys & Games"), which the
        // vendor's own path may never mention at all.
        $bestSubcategory = null;
        $bestSubcategoryScore = 0;

        foreach ($categories as $category) {
            foreach ($category->subcategories as $subcategory) {
                $score = count(array_intersect($this->significantWords($subcategory->name), $hintWords));

                if ($score > $bestSubcategoryScore) {
                    $bestSubcategoryScore = $score;
                    $bestSubcategory = $subcategory;
                }
            }
        }

        if ($bestSubcategory) {
            return ['category_id' => $bestSubcategory->category_id, 'subcategory_id' => $bestSubcategory->id];
        }

        $bestCategory = null;
        $bestCategoryScore = 0;

        foreach ($categories as $category) {
            $score = count(array_intersect($this->significantWords($category->name), $hintWords));

            if ($score > $bestCategoryScore) {
                $bestCategoryScore = $score;
                $bestCategory = $category;
            }
        }

        return [
            'category_id' => $bestCategory?->id,
            'subcategory_id' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $text): array
    {
        $stopwords = ['and', 'the', 'for', 'of', 'with', 'a', 'an', 'in', 'on', 'to'];
        $words = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn ($word) => strlen($word) >= 3 && ! in_array($word, $stopwords, true)
        )));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['required', 'exists:vendor_product_candidates,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'custom_images' => ['nullable', 'array'],
            'custom_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:'.self::MAX_VIDEO_KB],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.list' => ['nullable', 'boolean'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.vendor_sku' => ['nullable', 'string', 'max:100'],
            'variants.*.option_label' => ['required', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.size' => ['nullable', 'string', 'max:100'],
            'variants.*.cost_price' => ['required', 'numeric', 'min:0'],
            'variants.*.weight_lb' => ['nullable', 'numeric', 'min:0'],
            'variants.*.image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants.*.vendor_image' => ['nullable', 'string'],
        ]);

        $candidate = VendorProductCandidate::findOrFail($validated['candidate_id']);

        $listedVariants = collect($validated['variants'])->filter(fn ($v) => ! empty($v['list']));

        abort_if($listedVariants->isEmpty(), 422, 'Select at least one variant to list.');

        $isActive = $request->boolean('is_active');
        $imageUrls = $this->mergeImages($validated['images'] ?? [], $request->file('custom_images', []));
        $videoPath = $request->hasFile('video') ? $request->file('video')->store('products/videos', 'public') : null;

        $variantImages = $listedVariants->mapWithKeys(fn ($variant, $i) => [
            $i => $this->resolveVariantImage($request, "variants.{$i}.image", $variant['vendor_image'] ?? null),
        ]);

        $product = DB::transaction(function () use ($validated, $candidate, $listedVariants, $isActive, $imageUrls, $videoPath, $variantImages) {
            $product = Product::create([
                'category_id' => $validated['category_id'],
                'subcategory_id' => $validated['subcategory_id'] ?? null,
                'vendor_product_candidate_id' => $candidate->id,
                'source_vendor' => $candidate->vendor,
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title']),
                'description' => DescriptionSanitizer::sanitize($validated['description'] ?? null),
                'price' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'] ?? null,
                'images' => $imageUrls,
                'video_path' => $videoPath,
                'is_active' => $isActive,
            ]);

            foreach ($listedVariants as $i => $variant) {
                // Draft designs are saved pre-publish keyed by the vendor's
                // own variant SKU (no real ProductVariant row exists yet at
                // that point) — hand each one off to its matching variant
                // now that it's actually being created.
                $draftDesign = $variant['vendor_sku'] ?? null
                    ? ($candidate->design_data[$variant['vendor_sku']] ?? null)
                    : null;

                $product->variants()->create([
                    'sku' => $variant['sku'],
                    'vendor_sku' => $variant['vendor_sku'] ?? null,
                    'option_label' => $variant['option_label'],
                    'image' => $variantImages[$i] ?? null,
                    'color' => $variant['color'] ?? null,
                    'size' => $variant['size'] ?? null,
                    'cost_price' => $variant['cost_price'],
                    'weight_lb' => $variant['weight_lb'] ?? null,
                    'design_data' => $draftDesign,
                    'stock_status' => 'in_stock',
                    'is_active' => true,
                ]);
            }

            $candidate->update(['status' => 'listed']);

            return $product;
        });

        return redirect()->route('admin.products')->with('status', "\"{$product->title}\" published to the store.");
    }

    public function edit(Product $product): View
    {
        $product->load('variants', 'vendorProductCandidate');
        $categories = Category::with('subcategories')->orderBy('sort_order')->get();

        $usedVendorSkus = $product->variants->pluck('vendor_sku')->filter()->all();
        $availableVendorVariants = collect($product->vendorProductCandidate?->raw_payload['variants'] ?? [])
            ->reject(fn ($variant) => in_array($variant['vendor_sku'] ?? null, $usedVendorSkus, true))
            ->values();

        $designPlacements = $product->source_vendor === 'printful' && $product->vendorProductCandidate
            ? $this->printful->getPrintfileTemplate($product->vendorProductCandidate->vendor_external_id)
            : [];

        // Every photo already associated with this product — the vendor's
        // own per-variant images and overall preview, plus whatever is
        // currently in the product's own gallery (which can include
        // custom photos the admin already uploaded there) — so a variant
        // row can be pointed at any of them, not just whichever one was
        // auto-assigned at publish time or a freshly uploaded new file.
        $pickableImages = collect($product->vendorProductCandidate?->raw_payload['variants'] ?? [])
            ->pluck('image')
            ->filter()
            ->unique()
            ->values();
        if ($product->vendorProductCandidate?->preview_image_url) {
            $pickableImages = collect([$product->vendorProductCandidate->preview_image_url])->merge($pickableImages)->unique()->values();
        }
        $pickableImages = collect($product->images ?? [])->merge($pickableImages)->unique()->values();

        return view('admin.products.edit', compact('product', 'categories', 'availableVendorVariants', 'designPlacements', 'pickableImages'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'keep_images' => ['nullable', 'array'],
            'keep_images.*' => ['string'],
            'custom_images' => ['nullable', 'array'],
            'custom_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_video' => ['nullable', 'boolean'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:'.self::MAX_VIDEO_KB],
            'existing_variants' => ['nullable', 'array'],
            'existing_variants.*.option_label' => ['required', 'string', 'max:255'],
            'existing_variants.*.color' => ['nullable', 'string', 'max:100'],
            'existing_variants.*.size' => ['nullable', 'string', 'max:100'],
            'existing_variants.*.sku' => ['required', 'string', 'max:100'],
            'existing_variants.*.weight_lb' => ['nullable', 'numeric', 'min:0'],
            'existing_variants.*.is_active' => ['nullable', 'boolean'],
            'existing_variants.*.image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'existing_variants.*.vendor_image' => ['nullable', 'string'],
            'new_variants' => ['nullable', 'array'],
            'new_variants.*.add' => ['nullable', 'boolean'],
            'new_variants.*.vendor_sku' => ['nullable', 'string'],
            'new_variants.*.vendor_image' => ['nullable', 'string'],
            'new_variants.*.option_label' => ['nullable', 'string', 'max:255'],
            'new_variants.*.color' => ['nullable', 'string', 'max:100'],
            'new_variants.*.size' => ['nullable', 'string', 'max:100'],
            'new_variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'new_variants.*.weight_lb' => ['nullable', 'numeric', 'min:0'],
            'new_variants.*.sku' => ['nullable', 'string', 'max:100'],
            'new_variants.*.image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $skuError = $this->findSkuConflict($product, $validated['existing_variants'] ?? [], $validated['new_variants'] ?? []);
        if ($skuError) {
            return back()->withErrors(['variants' => $skuError])->withInput();
        }

        $oldPrice = (float) $product->price;

        $videoPath = $product->video_path;
        if ($request->hasFile('video')) {
            if ($videoPath) {
                Storage::disk('public')->delete($videoPath);
            }
            $videoPath = $request->file('video')->store('products/videos', 'public');
        } elseif ($request->boolean('remove_video') && $videoPath) {
            Storage::disk('public')->delete($videoPath);
            $videoPath = null;
        }

        DB::transaction(function () use ($request, $validated, $product, $videoPath) {
            $product->update([
                'title' => $validated['title'],
                'description' => DescriptionSanitizer::sanitize($validated['description'] ?? null),
                'category_id' => $validated['category_id'],
                'subcategory_id' => $validated['subcategory_id'] ?? null,
                'price' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'] ?? null,
                'images' => $this->mergeImages($validated['keep_images'] ?? [], $request->file('custom_images', [])),
                'video_path' => $videoPath,
                'is_active' => $request->boolean('is_active'),
            ]);

            foreach ($validated['existing_variants'] ?? [] as $variantId => $data) {
                $variant = $product->variants->firstWhere('id', (int) $variantId);
                if (! $variant) {
                    continue;
                }

                $variant->update([
                    'option_label' => $data['option_label'],
                    'color' => $data['color'] ?? null,
                    'size' => $data['size'] ?? null,
                    'sku' => $data['sku'],
                    'weight_lb' => $data['weight_lb'] ?? null,
                    // A freshly picked vendor image (from the "Choose Vendor
                    // Image" picker) wins over the variant's current image,
                    // but an uploaded file always wins over either — see
                    // resolveVariantImage().
                    'image' => $this->resolveVariantImage($request, "existing_variants.{$variantId}.image", $data['vendor_image'] ?? $variant->image),
                    'is_active' => ! empty($data['is_active']),
                ]);
            }

            foreach ($validated['new_variants'] ?? [] as $i => $data) {
                if (empty($data['add'])) {
                    continue;
                }

                $product->variants()->create([
                    'sku' => $data['sku'],
                    'vendor_sku' => $data['vendor_sku'] ?? null,
                    'option_label' => $data['option_label'] ?? 'Variant',
                    'image' => $this->resolveVariantImage($request, "new_variants.{$i}.image", $data['vendor_image'] ?? null),
                    'color' => $data['color'] ?? null,
                    'size' => $data['size'] ?? null,
                    'cost_price' => $data['cost_price'] ?? 0,
                    'weight_lb' => $data['weight_lb'] ?? null,
                    'stock_status' => 'in_stock',
                    'is_active' => true,
                ]);
            }
        });

        if ($oldPrice !== (float) $validated['price']) {
            AuditLog::record(
                'Price Override',
                "Product \"{$product->title}\" (#{$product->id}): \${$oldPrice} → \${$validated['price']}"
            );
        }

        return back()->with('status', 'Product updated.');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $existingVariants
     * @param  array<int|string, array<string, mixed>>  $newVariants
     */
    private function findSkuConflict(Product $product, array $existingVariants, array $newVariants): ?string
    {
        $skus = [];

        foreach ($existingVariants as $variantId => $data) {
            $sku = $data['sku'];
            if (ProductVariant::where('sku', $sku)->where('id', '!=', (int) $variantId)->exists()) {
                return "SKU \"{$sku}\" is already used by another variant.";
            }
            $skus[] = $sku;
        }

        foreach ($newVariants as $data) {
            if (empty($data['add'])) {
                continue;
            }
            $sku = $data['sku'] ?? null;
            if (blank($sku)) {
                return 'Enter an internal SKU for every variant you add.';
            }
            if (ProductVariant::where('sku', $sku)->exists()) {
                return "SKU \"{$sku}\" is already used by another variant.";
            }
            $skus[] = $sku;
        }

        if (count($skus) !== count(array_unique($skus))) {
            return 'Each variant needs a unique internal SKU.';
        }

        return null;
    }

    private function resolveVariantImage(Request $request, string $fileKey, ?string $fallbackUrl): ?string
    {
        if ($request->hasFile($fileKey)) {
            return Storage::disk('public')->url($request->file($fileKey)->store('products/variants', 'public'));
        }

        return $fallbackUrl ?: null;
    }

    /**
     * @param  array<int, string>  $selectedUrls
     * @param  array<int, UploadedFile>  $uploadedFiles
     * @return array<int, string>
     */
    private function mergeImages(array $selectedUrls, array $uploadedFiles): array
    {
        $imageUrls = array_values($selectedUrls);

        foreach ($uploadedFiles as $file) {
            $imageUrls[] = Storage::disk('public')->url($file->store('products', 'public'));
        }

        return $imageUrls;
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
}
