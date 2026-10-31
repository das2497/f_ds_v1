<?php

namespace App\Services\Seo;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds schema.org JSON-LD graphs for the storefront.
 *
 * Every method returns a plain array that the store layout encodes into a
 * `<script type="application/ld+json">` block, so the shapes here are the
 * only place that needs to know about schema.org vocabulary.
 */
class StructuredData
{
    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            '@id' => url('/').'#organization',
            'name' => config('store.name'),
            'url' => url('/'),
            'logo' => asset('images/logo.jpeg'),
            'description' => config('store.description'),
            'areaServed' => config('store.country'),
        ];

        if ($email = config('store.contact.email')) {
            $graph['contactPoint'] = array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $email,
                'telephone' => config('store.contact.phone'),
                'availableLanguage' => 'English',
            ]);
        }

        if ($social = array_values(config('store.social', []))) {
            $graph['sameAs'] = $social;
        }

        return $graph;
    }

    /**
     * The site-level graph, including the search action that lets Google
     * render a sitelinks search box for the storefront.
     *
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => config('store.name'),
            'url' => url('/'),
            'description' => config('store.description'),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('products.index').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: ?string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index) => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'] ?? null,
            ]))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(Product $product): array
    {
        $variants = $product->variants->where('is_active', true);
        $inStock = $variants->contains(fn ($variant) => $variant->stock_status !== 'out_of_stock');
        $prices = $variants->pluck('price')->filter()->map(fn ($price) => (float) $price);
        $skus = $variants->pluck('sku')->filter();

        $offer = [
            '@type' => 'AggregateOffer',
            'url' => route('products.show', $product),
            'priceCurrency' => config('store.currency'),
            'lowPrice' => number_format($prices->min() ?? (float) $product->price, 2, '.', ''),
            'highPrice' => number_format($prices->max() ?? (float) $product->price, 2, '.', ''),
            'offerCount' => max($variants->count(), 1),
            'availability' => $inStock
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@id' => url('/').'#organization'],
        ];

        $reviews = $product->relationLoaded('reviews') ? $product->reviews : collect();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'description' => self::excerpt($product->description, 300),
            'image' => collect($product->images ?? [])->filter()->values()->all() ?: null,
            'sku' => $skus->first(),
            'category' => $product->category?->name,
            'brand' => [
                '@type' => 'Brand',
                'name' => config('store.name'),
            ],
            'offers' => $offer,
            'aggregateRating' => $reviews->isNotEmpty() ? [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($reviews->avg('rating'), 1),
                'reviewCount' => $reviews->count(),
            ] : null,
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * A listing page's products, so search engines can read the result set
     * as an ordered collection rather than guessing from the markup.
     *
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    public static function productList(Collection $products, string $name, int $startPosition = 1): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => $products->count(),
            'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                '@type' => 'ListItem',
                'position' => $startPosition + $index,
                'url' => route('products.show', $product),
                'name' => $product->title,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function policyPage(string $name, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $name,
            'url' => $url,
            'isPartOf' => ['@id' => url('/').'#website'],
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    /**
     * Flattens rich-text into a plain meta-description-safe sentence.
     */
    public static function excerpt(?string $html, int $limit = 160): ?string
    {
        if (blank($html)) {
            return null;
        }

        $text = Str::squish(strip_tags($html));

        return $text === '' ? null : Str::limit($text, $limit);
    }
}
