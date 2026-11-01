<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeProduct(string $title = 'Test Product', bool $isActive = true): Product
    {
        return Product::create([
            'source_vendor' => 'printful',
            'title' => $title,
            'slug' => Str::slug($title).'-'.random_int(1000, 9999),
            'price' => 20,
            'is_active' => $isActive,
        ]);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_product(): void
    {
        $actor = $this->admin();
        $product = $this->makeProduct(isActive: true);

        $this->actingAs($actor)->put(route('admin.products.toggle-status', $product))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Product Deactivated']);

        $this->actingAs($actor)->put(route('admin.products.toggle-status', $product))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue($product->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Product Activated']);
    }

    public function test_an_admin_can_delete_a_product(): void
    {
        $actor = $this->admin();
        $product = $this->makeProduct();
        $variant = $product->variants()->create([
            'sku' => 'SKU-'.random_int(1000, 9999),
            'option_label' => 'Default',
            'cost_price' => 5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($actor)->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products'));
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Product Deleted']);
    }

    public function test_a_non_admin_cannot_toggle_or_delete_a_product(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $product = $this->makeProduct();

        $this->actingAs($user)->put(route('admin.products.toggle-status', $product))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.products.destroy', $product))->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_a_guest_is_redirected_away_from_product_management_actions(): void
    {
        $product = $this->makeProduct();

        $this->put(route('admin.products.toggle-status', $product))->assertRedirect(route('login'));
        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('login'));
    }
}
