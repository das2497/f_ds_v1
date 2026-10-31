<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProductCandidate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDesignCanvasTest extends TestCase
{
    use RefreshDatabase;

    // A valid 1x1 transparent PNG, standing in for a real exported design.
    private const FAKE_PNG_BASE64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function printfulCandidate(): VendorProductCandidate
    {
        return VendorProductCandidate::create([
            'vendor' => 'printful',
            'vendor_external_id' => '300',
            'raw_payload' => [
                'title' => 'Black Glossy Mug',
                'variants' => [
                    ['vendor_sku' => '9323', 'option_label' => 'Black / 11 oz', 'color' => 'Black', 'size' => '11 oz', 'cost_price' => 8.11, 'weight_lb' => null, 'image' => null],
                    ['vendor_sku' => '9324', 'option_label' => 'Black / 15 oz', 'color' => 'Black', 'size' => '15 oz', 'cost_price' => 9.50, 'weight_lb' => null, 'image' => null],
                ],
            ],
            'title' => 'Black Glossy Mug',
            'base_cost' => 8.11,
            'preview_image_url' => 'https://files.cdn.printful.com/preview.jpg',
            'category_hint' => 'Mug',
            'variant_count' => 2,
            'status' => 'selected',
        ]);
    }

    private function designPayload(string $placement = 'front', ?string $vendorSku = '9323'): array
    {
        $payload = [
            'placement' => $placement,
            'canvas_json' => json_encode(['objects' => []]),
            'print_file_data' => self::FAKE_PNG_BASE64,
            'width' => 1800,
            'height' => 2400,
        ];

        if ($vendorSku !== null) {
            $payload['vendor_sku'] = $vendorSku;
        }

        return $payload;
    }

    public function test_an_admin_can_upload_an_image_for_the_canvas_to_reference_by_url(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->postJson(route('admin.product-designs.upload-image'), [
            'image' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);

        $path = ltrim(parse_url($response->json('url'), PHP_URL_PATH), '/');
        Storage::disk('public')->assertExists(str_replace('storage/', '', $path));
    }

    public function test_an_admin_can_save_a_draft_design_for_one_variant_of_a_printful_candidate(): void
    {
        Storage::fake('public');

        $candidate = $this->printfulCandidate();

        $response = $this->actingAs($this->admin())
            ->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('front', '9323'));

        $response->assertOk()->assertJson(['status' => 'ok', 'placement' => 'front']);

        $candidate->refresh();

        $this->assertArrayHasKey('9323', $candidate->design_data);
        $this->assertArrayHasKey('front', $candidate->design_data['9323']);
        $this->assertSame(1800, $candidate->design_data['9323']['front']['width']);

        $path = ltrim(parse_url($candidate->design_data['9323']['front']['print_file_url'], PHP_URL_PATH), '/');
        Storage::disk('public')->assertExists(str_replace('storage/', '', $path));
    }

    public function test_saving_designs_for_different_variants_does_not_clobber_each_other(): void
    {
        Storage::fake('public');

        $candidate = $this->printfulCandidate();
        $actor = $this->admin();

        $this->actingAs($actor)->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('front', '9323'))->assertOk();
        $this->actingAs($actor)->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('front', '9324'))->assertOk();

        $candidate->refresh();

        $this->assertArrayHasKey('9323', $candidate->design_data);
        $this->assertArrayHasKey('9324', $candidate->design_data);
    }

    public function test_saving_a_second_placement_for_the_same_variant_does_not_clobber_the_first(): void
    {
        Storage::fake('public');

        $candidate = $this->printfulCandidate();
        $actor = $this->admin();

        $this->actingAs($actor)->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('front', '9323'))->assertOk();
        $this->actingAs($actor)->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('back', '9323'))->assertOk();

        $candidate->refresh();

        $this->assertArrayHasKey('front', $candidate->design_data['9323']);
        $this->assertArrayHasKey('back', $candidate->design_data['9323']);
    }

    public function test_cj_candidates_cannot_use_the_design_canvas(): void
    {
        $candidate = VendorProductCandidate::create([
            'vendor' => 'cj',
            'vendor_external_id' => '123',
            'raw_payload' => [],
            'title' => 'RC Car',
            'base_cost' => 15,
            'variant_count' => 1,
            'status' => 'selected',
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload())
            ->assertForbidden();
    }

    public function test_saving_a_draft_design_requires_a_vendor_sku(): void
    {
        Storage::fake('public');

        $candidate = $this->printfulCandidate();

        $this->actingAs($this->admin())
            ->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('front', null))
            ->assertJsonValidationErrors('vendor_sku');
    }

    public function test_saving_a_design_rejects_an_oversized_canvas_json_payload(): void
    {
        Storage::fake('public');

        $candidate = $this->printfulCandidate();
        $payload = $this->designPayload();
        $payload['canvas_json'] = json_encode(['objects' => [['src' => str_repeat('a', 250000)]]]);

        $this->actingAs($this->admin())
            ->putJson(route('admin.candidates.design.update', $candidate), $payload)
            ->assertJsonValidationErrors('canvas_json');
    }

    public function test_publishing_a_candidate_hands_each_variants_draft_design_to_its_matching_product_variant(): void
    {
        Storage::fake('public');

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $candidate = $this->printfulCandidate();
        $actor = $this->admin();

        $this->actingAs($actor)->putJson(route('admin.candidates.design.update', $candidate), $this->designPayload('front', '9323'))->assertOk();
        $candidate->refresh();

        $this->actingAs($actor)->post(route('admin.products.store'), [
            'candidate_id' => $candidate->id,
            'title' => 'Black Glossy Mug',
            'category_id' => $category->id,
            'price' => 20.28,
            'is_active' => '1',
            'variants' => [
                ['list' => '1', 'sku' => 'SHB-MUG-1', 'vendor_sku' => '9323', 'option_label' => 'Black / 11 oz', 'color' => 'Black', 'size' => '11 oz', 'cost_price' => 8.11, 'weight_lb' => null],
                ['list' => '1', 'sku' => 'SHB-MUG-2', 'vendor_sku' => '9324', 'option_label' => 'Black / 15 oz', 'color' => 'Black', 'size' => '15 oz', 'cost_price' => 9.5, 'weight_lb' => null],
            ],
        ])->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Black Glossy Mug')->firstOrFail();
        $designedVariant = $product->variants()->where('vendor_sku', '9323')->firstOrFail();
        $undesignedVariant = $product->variants()->where('vendor_sku', '9324')->firstOrFail();

        $this->assertArrayHasKey('front', $designedVariant->design_data);
        $this->assertSame($candidate->design_data['9323']['front']['print_file_url'], $designedVariant->design_data['front']['print_file_url']);
        $this->assertNull($undesignedVariant->design_data);
    }

    public function test_an_admin_can_save_a_design_directly_against_a_published_variant(): void
    {
        Storage::fake('public');

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $product = Product::create([
            'category_id' => $category->id,
            'source_vendor' => 'printful',
            'title' => 'Black Glossy Mug',
            'slug' => 'black-glossy-mug',
            'price' => 20.28,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHB-MUG-1',
            'vendor_sku' => '9323',
            'option_label' => 'Black / 11 oz',
            'cost_price' => 8.11,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('admin.product-variants.design.update', $variant), $this->designPayload('front', null))
            ->assertOk();

        $this->assertArrayHasKey('front', $variant->fresh()->design_data);
    }

    public function test_saving_a_design_for_one_variant_does_not_affect_a_sibling_variant(): void
    {
        Storage::fake('public');

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $product = Product::create([
            'category_id' => $category->id,
            'source_vendor' => 'printful',
            'title' => 'Black Glossy Mug',
            'slug' => 'black-glossy-mug',
            'price' => 20.28,
            'images' => [],
            'is_active' => true,
        ]);
        $variantA = $product->variants()->create(['sku' => 'SHB-MUG-1', 'vendor_sku' => '9323', 'option_label' => 'Black / 11 oz', 'cost_price' => 8.11, 'stock_status' => 'in_stock', 'is_active' => true]);
        $variantB = $product->variants()->create(['sku' => 'SHB-MUG-2', 'vendor_sku' => '9324', 'option_label' => 'Black / 15 oz', 'cost_price' => 9.5, 'stock_status' => 'in_stock', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson(route('admin.product-variants.design.update', $variantA), $this->designPayload('front', null))
            ->assertOk();

        $this->assertNotNull($variantA->fresh()->design_data);
        $this->assertNull($variantB->fresh()->design_data);
    }

    public function test_cj_sourced_products_cannot_use_the_design_canvas(): void
    {
        $category = Category::create(['name' => 'Toys', 'slug' => 'toys']);
        $product = Product::create([
            'category_id' => $category->id,
            'source_vendor' => 'cj',
            'title' => 'RC Car',
            'slug' => 'rc-car',
            'price' => 25,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHB-RC-1',
            'option_label' => 'Red',
            'cost_price' => 15,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('admin.product-variants.design.update', $variant), $this->designPayload('front', null))
            ->assertForbidden();
    }

    public function test_the_customize_page_renders_a_design_button_per_variant_for_a_printful_candidate(): void
    {
        $candidate = $this->printfulCandidate();

        $response = $this->actingAs($this->admin())->get(route('admin.products.create', $candidate));

        $response->assertOk();
        $response->assertSee('designModal-candidate-0', false);
        $response->assertSee('designModal-candidate-1', false);
        $response->assertSee('Add Text');
        $response->assertSee('Front');
    }

    public function test_the_customize_page_does_not_render_design_buttons_for_a_cj_candidate(): void
    {
        $candidate = VendorProductCandidate::create([
            'vendor' => 'cj',
            'vendor_external_id' => '123',
            'raw_payload' => ['variants' => []],
            'title' => 'RC Car',
            'base_cost' => 15,
            'variant_count' => 1,
            'status' => 'selected',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.create', $candidate));

        $response->assertOk();
        $response->assertDontSee('js-design-canvas-modal', false);
    }

    public function test_the_edit_page_renders_a_design_button_per_variant_for_a_printful_product(): void
    {
        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $product = Product::create([
            'category_id' => $category->id,
            'source_vendor' => 'printful',
            'title' => 'Black Glossy Mug',
            'slug' => 'black-glossy-mug',
            'price' => 20.28,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHB-MUG-1',
            'vendor_sku' => '9323',
            'option_label' => 'Black / 11 oz',
            'cost_price' => 8.11,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee('designModal-variant-'.$variant->id, false);
    }
}
