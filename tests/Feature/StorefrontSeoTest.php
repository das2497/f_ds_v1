<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'is_active' => true],
        );

        $product = Product::create(array_merge([
            'category_id' => $category->id,
            'title' => 'Tough Phone Case',
            'slug' => 'tough-phone-case',
            'description' => '<p>A <strong>durable</strong> case that protects against drops.</p>',
            'price' => 24.50,
            'images' => ['https://cdn.example.com/case.jpg'],
            'is_active' => true,
        ], $attributes));

        $product->variants()->create([
            'sku' => 'SHB-'.strtoupper($product->slug),
            'option_label' => 'Default',
            'cost_price' => 9,
            'price' => 24.50,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        return $product->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonLdOfType(string $html, string $type): ?array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        foreach ($matches[1] as $block) {
            $decoded = json_decode($block, true);

            if (is_array($decoded) && ($decoded['@type'] ?? null) === $type) {
                return $decoded;
            }
        }

        return null;
    }

    public function test_the_home_page_has_one_h1_and_core_meta_tags(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'The home page must have exactly one <h1>.');
        $this->assertStringContainsString('<meta name="description"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('home').'"', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertNotNull($this->jsonLdOfType($html, 'OnlineStore'));
        $this->assertNotNull($this->jsonLdOfType($html, 'WebSite'));
    }

    public function test_a_product_page_emits_product_and_breadcrumb_structured_data(): void
    {
        $product = $this->product();

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $schema = $this->jsonLdOfType($html, 'Product');
        $this->assertNotNull($schema);
        $this->assertSame('Tough Phone Case', $schema['name']);
        $this->assertSame('SHB-TOUGH-PHONE-CASE', $schema['sku']);
        $this->assertSame('Electronics', $schema['category']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame('24.50', $schema['offers']['lowPrice']);

        // The meta description is built from the rich-text body, so it must
        // arrive as plain text rather than markup.
        $this->assertStringContainsString('A durable case that protects against drops.', $schema['description']);

        $breadcrumbs = $this->jsonLdOfType($html, 'BreadcrumbList');
        $this->assertNotNull($breadcrumbs);
        $this->assertSame(
            ['Home', 'Electronics', 'Tough Phone Case'],
            array_column($breadcrumbs['itemListElement'], 'name'),
        );
    }

    public function test_an_out_of_stock_product_reports_out_of_stock_availability(): void
    {
        $product = $this->product(['slug' => 'sold-out-case']);
        $product->variants()->update(['stock_status' => 'out_of_stock']);

        $html = $this->get(route('products.show', $product->fresh()))->assertOk()->getContent();

        $this->assertSame(
            'https://schema.org/OutOfStock',
            $this->jsonLdOfType($html, 'Product')['offers']['availability'],
        );
    }

    public function test_the_plain_product_listing_is_indexable(): void
    {
        $this->product();

        $html = $this->get(route('products.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<meta name="robots"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('products.index').'"', $html);
    }

    public function test_search_and_facet_listings_are_excluded_from_the_index(): void
    {
        $this->product();

        $html = $this->get(route('products.index', ['q' => 'case', 'on_sale' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        // The canonical still points at the clean listing so link equity
        // is not spread across every filter combination.
        $this->assertStringContainsString('<link rel="canonical" href="'.route('products.index').'"', $html);
    }

    public function test_private_pages_are_not_indexable(): void
    {
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_the_sitemap_lists_active_products_and_policies(): void
    {
        $active = $this->product();
        $hidden = $this->product(['slug' => 'hidden-case', 'title' => 'Hidden Case', 'is_active' => false]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>'.route('products.show', $active).'</loc>', false);
        $response->assertSee('<loc>'.route('policies.privacy').'</loc>', false);
        $response->assertDontSee('hidden-case');
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', trim($response->getContent()));
    }

    public function test_robots_txt_blocks_everything_outside_production(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
    }

    public function test_robots_txt_allows_crawling_and_advertises_the_sitemap_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $content = $this->get(route('robots'))->assertOk()->getContent();

        $this->assertStringContainsString("User-agent: *\nAllow: /", $content);
        $this->assertStringContainsString('Disallow: /checkout', $content);
        $this->assertStringContainsString('Disallow: /account', $content);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $content);
    }

    public function test_the_policy_pages_render_with_their_own_metadata(): void
    {
        foreach (['policies.privacy', 'policies.terms', 'policies.shipping-refund'] as $name) {
            $html = $this->get(route($name))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$name} must have exactly one <h1>.");
            $this->assertStringContainsString('<link rel="canonical" href="'.route($name).'"', $html);
            $this->assertNotNull($this->jsonLdOfType($html, 'WebPage'), "{$name} is missing WebPage structured data.");
        }

        // Entities must survive Blade escaping rather than double-encoding.
        $this->get(route('policies.shipping-refund'))
            ->assertSee('Shipping &amp; Refund Policy', false)
            ->assertDontSee('&amp;amp;', false);
    }
}
