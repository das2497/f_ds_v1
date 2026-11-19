<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use App\Models\VendorProductCandidate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Regression test: the AliExpress extension's scraped payload only
     * ever included option_label/color/image/cost_price for a variant —
     * no size/vendor_sku/weight_lb keys at all. The "Add More Variants"
     * section of this view reads straight from the candidate's raw
     * payload and used to assume every one of those keys was always
     * present ($variant['size'] with no `??` fallback), which 500'd with
     * "Undefined array key" the moment an admin opened an AliExpress
     * product to edit it.
     */
    public function test_editing_a_product_sourced_from_an_incomplete_vendor_payload_does_not_error(): void
    {
        $admin = $this->admin();

        $candidate = VendorProductCandidate::create([
            'vendor' => 'aliexpress',
            'vendor_external_id' => '1005006260949497',
            'raw_payload' => [
                'vendor_external_id' => '1005006260949497',
                'title' => 'Blonde Wig',
                'variants' => [
                    // Deliberately missing size/vendor_sku/weight_lb, like
                    // the extension's real payload shape.
                    ['option_label' => 'SS170-2', 'color' => 'SS170-2', 'image' => null, 'cost_price' => 12.5],
                ],
            ],
            'title' => 'Blonde Wig',
            'base_cost' => 12.5,
            'variant_count' => 1,
            'status' => 'listed',
        ]);

        $product = Product::create([
            'vendor_product_candidate_id' => $candidate->id,
            'source_vendor' => 'aliexpress',
            'title' => 'Blonde Wig',
            'slug' => 'blonde-wig',
            'price' => 12.5,
            'images' => [],
            'is_active' => false,
        ]);

        $product->variants()->create([
            'sku' => 'ALI-1005006260949497-1',
            'option_label' => 'SS170-2',
            'color' => 'SS170-2',
            'cost_price' => 12.5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee('Blonde Wig');
    }

    /**
     * Regression test: AliExpress imports every scraped variant
     * immediately (there's no "tick which ones to list" step like
     * Printful/CJ candidates go through), and its variants never carry a
     * vendor_sku. The "Add More Variants" section used to match on
     * vendor_sku to figure out what's already been added — which always
     * matched nothing for AliExpress, so a variant already live on the
     * product kept showing up a second time as something "available to
     * add".
     */
    public function test_an_aliexpress_products_already_added_variant_is_not_offered_again(): void
    {
        $admin = $this->admin();

        $candidate = VendorProductCandidate::create([
            'vendor' => 'aliexpress',
            'vendor_external_id' => '1005006260949497',
            'raw_payload' => [
                'vendor_external_id' => '1005006260949497',
                'title' => 'Blonde Wig',
                'variants' => [
                    ['option_label' => 'White', 'color' => 'White', 'image' => null, 'cost_price' => 12.5],
                    ['option_label' => 'Black', 'color' => 'Black', 'image' => null, 'cost_price' => 12.5],
                ],
            ],
            'title' => 'Blonde Wig',
            'base_cost' => 12.5,
            'variant_count' => 2,
            'status' => 'listed',
        ]);

        $product = Product::create([
            'vendor_product_candidate_id' => $candidate->id,
            'source_vendor' => 'aliexpress',
            'title' => 'Blonde Wig',
            'slug' => 'blonde-wig',
            'price' => 12.5,
            'images' => [],
            'is_active' => false,
        ]);

        foreach (['White', 'Black'] as $i => $label) {
            $product->variants()->create([
                'sku' => 'ALI-1005006260949497-'.($i + 1),
                'option_label' => $label,
                'color' => $label,
                'cost_price' => 12.5,
                'stock_status' => 'in_stock',
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertDontSee('Add More Variants');
    }

    public function test_the_products_source_link_is_shown_when_present(): void
    {
        $admin = $this->admin();

        $product = Product::create([
            'source_vendor' => 'aliexpress',
            'source_url' => 'https://www.aliexpress.com/item/1005006260949497.html',
            'title' => 'Blonde Wig',
            'slug' => 'blonde-wig-2',
            'price' => 12.5,
            'images' => [],
            'is_active' => false,
        ]);
        $product->variants()->create([
            'sku' => 'ALI-1005006260949497-1',
            'option_label' => 'Default',
            'cost_price' => 12.5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee('https://www.aliexpress.com/item/1005006260949497.html', false);
    }
}
