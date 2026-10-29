<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailSwatchTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
    }

    public function test_a_non_css_color_label_renders_as_a_text_pill_not_an_invisible_dot(): void
    {
        $product = Product::create([
            'category_id' => $this->category()->id,
            'title' => 'Tough Case',
            'slug' => 'tough-case',
            'price' => 35.58,
            'images' => [],
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-GLOSSY', 'option_label' => 'Glossy', 'color' => 'Glossy', 'size' => null,
            'cost_price' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-RED', 'option_label' => 'Red', 'color' => 'Red', 'size' => null,
            'cost_price' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        // "Glossy" isn't a CSS color, so it must show as a readable label…
        $response->assertSee('swatch-color--label', false);
        $response->assertSee('>Glossy<', false);
        // …while "Red" still renders as an actual colored dot.
        $response->assertSee('background:red', false);
    }

    public function test_the_default_selected_swatch_matches_the_in_stock_variant_even_when_its_not_first(): void
    {
        $product = Product::create([
            'category_id' => $this->category()->id,
            'title' => 'Multi Color Widget',
            'slug' => 'multi-color-widget',
            'price' => 10,
            'images' => [],
            'is_active' => true,
        ]);
        // First-created variant is OUT of stock — the old code picked this
        // as the default-active swatch regardless, which could leave "Add
        // to Cart" disabled on page load even though Blue is available.
        $outOfStock = $product->variants()->create([
            'sku' => 'SHB-RED', 'option_label' => 'Red', 'color' => 'Red', 'size' => null,
            'cost_price' => 5, 'stock_status' => 'out_of_stock', 'is_active' => true,
        ]);
        $inStock = $product->variants()->create([
            'sku' => 'SHB-BLUE', 'option_label' => 'Blue', 'color' => 'Blue', 'size' => null,
            'cost_price' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        // Scoped to the hidden variant-id input specifically — a bare
        // value="N" search would also match the unrelated quantity input,
        // which always renders value="1".
        $response->assertSee('id="selectedVariantId" value="'.$inStock->id.'"', false);
        $response->assertDontSee('id="selectedVariantId" value="'.$outOfStock->id.'"', false);
    }
}
