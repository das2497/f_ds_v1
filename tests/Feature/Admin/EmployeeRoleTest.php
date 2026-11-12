<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeRoleTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
    }

    private function product(): Product
    {
        $product = Product::create([
            'category_id' => $this->category()->id,
            'source_vendor' => 'manual',
            'title' => 'Existing Product',
            'slug' => 'existing-product',
            'price' => 10,
            'images' => [],
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-EXIST',
            'option_label' => 'Default',
            'cost_price' => 5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_an_employee_can_view_and_create_products(): void
    {
        Storage::fake('public');
        $category = $this->category();

        $this->actingAs($this->employee())->get(route('admin.products'))->assertOk();
        $this->actingAs($this->employee())->get(route('admin.products.create-manual'))->assertOk();

        $response = $this->actingAs($this->employee())->post(route('admin.products.store-manual'), [
            'title' => 'Hand-Poured Soy Candle',
            'description' => 'A lovely candle.',
            'category_id' => $category->id,
            'price' => 24.99,
            'is_active' => '1',
            'variants' => [
                ['sku' => 'SHB-CANDLE-1', 'option_label' => 'Lavender', 'cost_price' => 6.50],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));
        $this->assertDatabaseHas('products', ['title' => 'Hand-Poured Soy Candle']);
    }

    public function test_an_employee_can_edit_and_update_a_product(): void
    {
        $product = $this->product();

        $this->actingAs($this->employee())->get(route('admin.products.edit', $product))->assertOk();

        $response = $this->actingAs($this->employee())->put(route('admin.products.update', $product), [
            'title' => 'Updated Title',
            'description' => $product->description ?? 'Updated description.',
            'category_id' => $product->category_id,
            'price' => 15,
            'is_active' => '1',
            'variants' => [
                ['id' => $product->variants->first()->id, 'sku' => 'SHB-EXIST', 'option_label' => 'Default', 'cost_price' => 5],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('Updated Title', $product->fresh()->title);
    }

    public function test_an_employee_cannot_toggle_status_or_delete_products(): void
    {
        $product = $this->product();
        $actor = $this->employee();

        $this->actingAs($actor)->put(route('admin.products.toggle-status', $product))->assertForbidden();
        $this->actingAs($actor)->delete(route('admin.products.destroy', $product))->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_an_employee_can_access_vendor_research_and_selected_products(): void
    {
        config(['services.cj.api_key' => 'test-key']);

        Http::fake([
            '*/authentication/getAccessToken' => Http::response(['data' => ['accessToken' => 'test-token']]),
            '*/product/listV2*' => Http::response([
                'data' => ['content' => [], 'totalPages' => 0, 'totalRecords' => 0],
            ]),
        ]);

        $actor = $this->employee();

        $this->actingAs($actor)->get(route('admin.products-research-cjd'))->assertOk();
        $this->actingAs($actor)->get(route('admin.products-selected'))->assertOk();
    }

    public function test_an_employee_cannot_access_other_admin_areas(): void
    {
        $actor = $this->employee();

        $this->actingAs($actor)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.customers'))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.staff'))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.ai-research'))->assertForbidden();
    }

    public function test_an_employees_home_route_is_the_products_page(): void
    {
        $actor = $this->employee();

        $this->assertSame('admin.products', $actor->homeRouteName());
    }
}
