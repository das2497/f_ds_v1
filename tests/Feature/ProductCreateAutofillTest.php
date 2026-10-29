<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\VendorProductCandidate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCreateAutofillTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_the_customize_form_preselects_the_closest_category_and_subcategory_from_the_vendor_hint(): void
    {
        $toys = Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);
        $toys->subcategories()->create(['name' => 'Outdoor Play', 'slug' => 'outdoor-play']);
        $toys->subcategories()->create(['name' => 'Board Games', 'slug' => 'board-games']);
        Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $candidate = VendorProductCandidate::create([
            'vendor' => 'cj',
            'vendor_external_id' => '1777263998816235520',
            'raw_payload' => ['variants' => []],
            'title' => 'Four Way Fall Resistant Children\'s Toys',
            'base_cost' => 51.97,
            'category_hint' => 'Sports & Entertainment / Outdoor Fun & Sports / Outdoor Play Equipment',
            'variant_count' => 0,
            'status' => 'selected',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.create', $candidate));

        $response->assertOk();
        $response->assertSee('value="'.$toys->id.'" data-subcategories', false);
        // The matched category option carries `selected`; it's the one right before the closing `>`.
        $response->assertSeeInOrder([
            'value="'.$toys->id.'"',
            'selected',
            'Toys &amp; Games',
        ], false);
    }

    public function test_the_customize_form_prefills_the_rich_text_editor_with_sanitized_cj_html(): void
    {
        Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);

        $candidate = VendorProductCandidate::create([
            'vendor' => 'cj',
            'vendor_external_id' => '123',
            'raw_payload' => [
                'variants' => [],
                'description' => '<ul><li>Superior Comfort for everyone.</li><li>Breathable and cool all year.</li></ul><script>alert(1)</script>',
            ],
            'title' => 'Memory Foam Topper',
            'base_cost' => 20,
            'category_hint' => 'Home Storage',
            'variant_count' => 0,
            'status' => 'selected',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.create', $candidate));

        $response->assertOk();
        // Rendered as the hidden textarea's HTML-escaped source — the
        // browser's own textarea.value decodes it back to real HTML, which
        // the rich-text editor's JS then loads into the visible surface.
        $response->assertSee('&lt;li&gt;Superior Comfort for everyone.&lt;/li&gt;', false);
        $response->assertSee('&lt;li&gt;Breathable and cool all year.&lt;/li&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('data-rich-text-editor', false);
    }

    public function test_the_customize_form_leaves_description_and_category_unselected_when_there_is_nothing_to_match(): void
    {
        Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $candidate = VendorProductCandidate::create([
            'vendor' => 'printful',
            'vendor_external_id' => '999',
            'raw_payload' => ['variants' => []],
            'title' => 'Unmatched Product',
            'base_cost' => 10,
            'category_hint' => null,
            'variant_count' => 0,
            'status' => 'selected',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.create', $candidate));

        $response->assertOk();
        $response->assertSee('Select category');
    }
}
