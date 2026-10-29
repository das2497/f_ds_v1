<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect();

        $urls->push([
            'loc' => route('home'),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ]);

        $urls->push([
            'loc' => route('products.index'),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ]);

        Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(fn (Category $category) => $urls->push([
                'loc' => route('products.index', ['cat' => $category->slug]),
                'lastmod' => $category->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ]));

        Subcategory::where('is_active', true)
            ->with('category:id,slug,is_active')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Subcategory $subcategory) => (bool) $subcategory->category?->is_active)
            ->each(fn (Subcategory $subcategory) => $urls->push([
                'loc' => route('products.index', ['cat' => $subcategory->category->slug, 'sub' => $subcategory->slug]),
                'lastmod' => $subcategory->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ]));

        Product::where('is_active', true)
            ->orderBy('id')
            ->each(fn (Product $product) => $urls->push([
                'loc' => route('products.show', $product),
                'lastmod' => $product->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ]));

        foreach (['policies.privacy', 'policies.terms', 'policies.shipping-refund'] as $policyRoute) {
            $urls->push([
                'loc' => route($policyRoute),
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ]);
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
