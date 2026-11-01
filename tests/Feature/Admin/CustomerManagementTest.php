<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeOrder(User $user, float $total, string $status = 'paid'): Order
    {
        return Order::create([
            'order_number' => 'SHB-'.random_int(10000, 99999),
            'user_id' => $user->id,
            'email' => $user->email,
            'shipping_name' => $user->name,
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_zip' => '78701',
            'shipping_country' => 'US',
            'shipping_method' => 'standard',
            'subtotal' => $total,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => $total,
            'currency' => 'USD',
            'status' => $status,
        ]);
    }

    public function test_the_page_lists_customers_with_order_count_and_lifetime_spend(): void
    {
        $actor = $this->admin();

        $customer = User::factory()->create(['name' => 'Cindy Customer']);
        $customer->assignRole('customer');
        $this->makeOrder($customer, 50, 'paid');
        $this->makeOrder($customer, 25, 'paid');
        $this->makeOrder($customer, 10, 'pending_payment');

        $staff = User::factory()->create(['name' => 'Staffy McStaff']);
        $staff->assignRole('admin');

        $response = $this->actingAs($actor)->get(route('admin.customers'));

        $response->assertOk();
        $response->assertSee('Cindy Customer');
        $response->assertSee('75.00');
        $response->assertDontSee('Staffy McStaff');
    }

    public function test_customers_can_be_searched_by_name_or_email(): void
    {
        $actor = $this->admin();

        $match = User::factory()->create(['name' => 'Findme Jones', 'email' => 'findme@example.com']);
        $match->assignRole('customer');

        $other = User::factory()->create(['name' => 'Nobody Else']);
        $other->assignRole('customer');

        $response = $this->actingAs($actor)->get(route('admin.customers', ['q' => 'findme']));

        $response->assertOk();
        $response->assertSee('Findme Jones');
        $response->assertDontSee('Nobody Else');
    }

    public function test_the_show_page_displays_a_customers_order_history_and_stats(): void
    {
        $actor = $this->admin();

        $customer = User::factory()->create(['name' => 'Cindy Customer']);
        $customer->assignRole('customer');
        $order = $this->makeOrder($customer, 50, 'paid');

        $response = $this->actingAs($actor)->get(route('admin.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Cindy Customer');
        $response->assertSee($order->order_number);
        $response->assertSee('50.00');
    }

    public function test_a_staff_member_cannot_be_viewed_as_a_customer(): void
    {
        $actor = $this->admin();

        $staff = User::factory()->create();
        $staff->assignRole('admin');

        $this->actingAs($actor)->get(route('admin.customers.show', $staff))->assertNotFound();
    }

    public function test_a_plain_customer_cannot_access_customer_management(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get(route('admin.customers'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_away_from_customer_management(): void
    {
        $this->get(route('admin.customers'))->assertRedirect(route('login'));
    }
}
