<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingFilterTest extends TestCase
{
    use RefreshDatabase;

    private ?Category $category = null;

    private function product(array $attributes): Product
    {
        $this->category ??= Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'images' => [],
            'is_active' => true,
        ], $attributes));
    }

    public function test_price_range_filters_out_products_outside_the_range(): void
    {
        $cheap = $this->product(['title' => 'Cheap Widget', 'slug' => 'cheap-widget', 'price' => 10]);
        $mid = $this->product(['title' => 'Mid Widget', 'slug' => 'mid-widget', 'price' => 50]);
        $expensive = $this->product(['title' => 'Expensive Widget', 'slug' => 'expensive-widget', 'price' => 200]);

        $response = $this->get(route('products.index', ['min_price' => 20, 'max_price' => 100]));

        $response->assertOk();
        $response->assertSee($mid->title);
        $response->assertDontSee($cheap->title);
        $response->assertDontSee($expensive->title);
    }

    public function test_on_sale_filter_only_shows_discounted_products(): void
    {
        $onSale = $this->product(['title' => 'Discounted Widget', 'slug' => 'discounted-widget', 'price' => 10, 'compare_at_price' => 20]);
        $fullPrice = $this->product(['title' => 'Full Price Widget', 'slug' => 'full-price-widget', 'price' => 10]);

        $response = $this->get(route('products.index', ['on_sale' => 1]));

        $response->assertOk();
        $response->assertSee($onSale->title);
        $response->assertDontSee($fullPrice->title);
    }

    public function test_in_stock_only_filter_excludes_products_with_no_available_variants(): void
    {
        $inStockProduct = $this->product(['title' => 'Available Widget', 'slug' => 'available-widget', 'price' => 10]);
        $inStockProduct->variants()->create([
            'sku' => 'SHB-A', 'option_label' => 'Default', 'cost_price' => 5,
            'stock_status' => 'in_stock', 'is_active' => true,
        ]);

        $outOfStockProduct = $this->product(['title' => 'Sold Out Widget', 'slug' => 'sold-out-widget', 'price' => 10]);
        $outOfStockProduct->variants()->create([
            'sku' => 'SHB-B', 'option_label' => 'Default', 'cost_price' => 5,
            'stock_status' => 'out_of_stock', 'is_active' => true,
        ]);

        $response = $this->get(route('products.index', ['in_stock' => 1]));

        $response->assertOk();
        $response->assertSee($inStockProduct->title);
        $response->assertDontSee($outOfStockProduct->title);
    }

    public function test_sort_by_price_ascending_orders_cheapest_first(): void
    {
        $this->product(['title' => 'Expensive Widget', 'slug' => 'expensive-widget', 'price' => 200]);
        $this->product(['title' => 'Cheap Widget', 'slug' => 'cheap-widget', 'price' => 10]);

        $response = $this->get(route('products.index', ['sort' => 'price_asc']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Cheap Widget') < strpos($content, 'Expensive Widget'));
    }

    public function test_an_unknown_sort_value_falls_back_to_newest_instead_of_erroring(): void
    {
        $this->product(['title' => 'Some Widget', 'slug' => 'some-widget', 'price' => 10]);

        $response = $this->get(route('products.index', ['sort' => 'not-a-real-sort']));

        $response->assertOk();
    }
}
