<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsDemandLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Services\Analytics\AttributionService;
use App\Services\Analytics\EventTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly EventTracker $tracker,
        private readonly AttributionService $attribution,
    ) {}

    private const SORT_OPTIONS = ['newest', 'price_asc', 'price_desc', 'name_asc'];

    public function index(Request $request): View
    {
        $category = $request->filled('cat') ? Category::where('slug', $request->string('cat'))->first() : null;
        $subcategory = $request->filled('sub') ? Subcategory::where('slug', $request->string('sub'))->first() : null;

        $sort = in_array($request->string('sort')->toString(), self::SORT_OPTIONS, true)
            ? $request->string('sort')->toString()
            : 'newest';
        $minPrice = $request->filled('min_price') ? max(0, (float) $request->input('min_price')) : null;
        $maxPrice = $request->filled('max_price') ? max(0, (float) $request->input('max_price')) : null;
        $inStockOnly = $request->boolean('in_stock');
        $onSale = $request->boolean('on_sale');

        $products = Product::query()
            ->with('variants')
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($subcategory, fn ($q) => $q->where('subcategory_id', $subcategory->id))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($minPrice !== null, fn ($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice !== null, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->when($onSale, fn ($q) => $q->whereColumn('compare_at_price', '>', 'price'))
            ->when($inStockOnly, fn ($q) => $q->whereHas(
                'variants',
                fn ($vq) => $vq->where('is_active', true)->where('stock_status', '!=', 'out_of_stock')
            ))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($sort === 'name_asc', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'newest', fn ($q) => $q->latest())
            ->paginate(12)
            ->withQueryString();

        if ($request->filled('q')) {
            AnalyticsDemandLog::create([
                'event_type' => 'search',
                'search_term' => $request->string('q')->toString(),
                'results_count' => $products->total(),
                'session_id' => $this->attribution->sessionId($request),
            ]);

            $this->tracker->track($request, 'search');
        }

        $allCategories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $subcategories = $category
            ? $category->subcategories()->where('is_active', true)->orderBy('sort_order')->get()
            : collect();

        return view('store.products', compact(
            'products', 'category', 'subcategory', 'allCategories', 'subcategories',
            'sort', 'minPrice', 'maxPrice', 'inStockOnly', 'onSale'
        ));
    }

    public function show(Request $request, Product $product): View|RedirectResponse
    {
        if (! $product->is_active) {
            return redirect()->route('home');
        }

        $product->load(['variants' => fn ($q) => $q->where('is_active', true), 'category']);

        $related = Product::with('variants')
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        AnalyticsDemandLog::create([
            'event_type' => 'view_item',
            'product_id' => $product->id,
            'session_id' => $this->attribution->sessionId($request),
        ]);

        $this->tracker->track($request, 'view_item', ['product_id' => $product->id]);

        return view('store.product-detail', compact('product', 'related'));
    }
}
