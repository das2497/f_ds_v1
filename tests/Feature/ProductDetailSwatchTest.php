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

    public function test_a_color_without_a_variant_image_renders_as_a_text_pill(): void
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
        // Neither variant has its own photo, so both fall back to a
        // readable text pill rather than a colored dot or a broken image.
        $response->assertSee('swatch-color--label', false);
        $response->assertSee('>Glossy<', false);
        $response->assertSee('>Red<', false);
    }

    public function test_a_color_with_a_variant_image_renders_as_an_image_swatch(): void
    {
        $product = Product::create([
            'category_id' => $this->category()->id,
            'title' => 'Photographed Widget',
            'slug' => 'photographed-widget',
            'price' => 19.99,
            'images' => [],
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-BLUE', 'option_label' => 'Blue', 'color' => 'Blue', 'size' => null,
            'image' => 'https://example.com/blue.jpg',
            'cost_price' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        // The color swatch is the variant's own photo, not a dot or a pill…
        $response->assertSee('swatch-color-image', false);
        $response->assertSee('src="https://example.com/blue.jpg"', false);
        // …and the "Color: Blue" label reflects the selected variant's name.
        $response->assertSee('Color: <span data-selected-color-label>Blue</span>', false);
    }

    public function test_every_variant_gets_its_own_swatch_even_when_colors_repeat(): void
    {
        $product = Product::create([
            'category_id' => $this->category()->id,
            'title' => 'Rug',
            'slug' => 'rug',
            'price' => 51.96,
            'images' => [],
            'is_active' => true,
        ]);
        // Two differently-sized variants sharing the same color — each has
        // its own photo and must render as its own swatch, not collapse
        // into a single "Grey and White" tile.
        $small = $product->variants()->create([
            'sku' => 'SHB-RUG-S', 'option_label' => 'Grey and White / Small', 'color' => 'Grey and White', 'size' => 'Small',
            'image' => 'https://example.com/rug-small.jpg',
            'cost_price' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);
        $large = $product->variants()->create([
            'sku' => 'SHB-RUG-L', 'option_label' => 'Grey and White / Large', 'color' => 'Grey and White', 'size' => 'Large',
            'image' => 'https://example.com/rug-large.jpg',
            'cost_price' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('data-variant-id="'.$small->id.'"', false);
        $response->assertSee('data-variant-id="'.$large->id.'"', false);
        $response->assertSee('src="https://example.com/rug-small.jpg"', false);
        $response->assertSee('src="https://example.com/rug-large.jpg"', false);
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
