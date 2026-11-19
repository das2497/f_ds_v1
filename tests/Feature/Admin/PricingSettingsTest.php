<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\PricingSettingsController;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_the_page_shows_the_default_margin_when_nothing_is_configured_yet(): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route('admin.pricing-settings'));

        $response->assertOk();
        $response->assertSee('value="150"', false);
    }

    public function test_a_super_admin_can_change_the_profit_margin(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.pricing-settings.update'), [
            'profit_margin_percent' => 80,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(80.0, PricingSettingsController::profitMarginPercent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Profit Margin Updated']);
    }

    public function test_the_margin_must_be_a_non_negative_number_within_range(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.pricing-settings.update'), [
            'profit_margin_percent' => -5,
        ])->assertSessionHasErrors('profit_margin_percent');

        $this->actingAs($this->superAdmin())->put(route('admin.pricing-settings.update'), [
            'profit_margin_percent' => 5000,
        ])->assertSessionHasErrors('profit_margin_percent');
    }

    public function test_a_regular_admin_cannot_view_or_change_pricing_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('admin.pricing-settings'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.pricing-settings.update'), ['profit_margin_percent' => 80])->assertForbidden();
    }

    public function test_a_guest_is_redirected_away_from_pricing_settings(): void
    {
        $this->get(route('admin.pricing-settings'))->assertRedirect(route('login'));
        $this->put(route('admin.pricing-settings.update'), [])->assertRedirect(route('login'));
    }

    public function test_profit_margin_percent_falls_back_to_the_default_when_unset(): void
    {
        $this->assertSame(150.0, PricingSettingsController::profitMarginPercent());

        AppSetting::set('pricing.profit_margin_percent', 42.5);

        $this->assertSame(42.5, PricingSettingsController::profitMarginPercent());
    }
}
