<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorResearchPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_the_catalog_page_renders_full_numbered_pagination_around_the_current_page(): void
    {
        config(['services.cj.api_key' => 'test-key']);

        Http::fake([
            '*/authentication/getAccessToken' => Http::response(['data' => ['accessToken' => 'test-token']]),
            '*/product/listV2*' => Http::response([
                'data' => [
                    'content' => [
                        ['productList' => [
                            ['id' => '1', 'nameEn' => 'Test Product', 'sellPrice' => 5.0, 'bigImage' => null],
                        ]],
                    ],
                    'totalPages' => 55,
                    'totalRecords' => 1100,
                ],
            ]),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.products-research-cjd', ['page' => 2]));

        $response->assertOk();

        // Pinned first/last page plus a window around the current page —
        // "1 2 3 4 ... 55" — with the far side collapsed behind an ellipsis.
        $response->assertSeeInOrder(['>1<', '>2<', '>3<', '>4<', '&hellip;', '>55<'], false);
        $response->assertSee('page=1', false);
        $response->assertSee('page=3', false);
        $response->assertSee('page=55', false);
    }

    public function test_the_catalog_page_falls_back_to_previous_next_only_when_the_vendor_reports_no_total_page_count(): void
    {
        config(['services.cj.api_key' => 'test-key']);

        Http::fake([
            '*/authentication/getAccessToken' => Http::response(['data' => ['accessToken' => 'test-token']]),
            '*/product/listV2*' => Http::response([
                'data' => [
                    'content' => [
                        ['productList' => array_fill(0, 20, ['id' => '1', 'nameEn' => 'Test Product', 'sellPrice' => 5.0, 'bigImage' => null])],
                    ],
                    'totalPages' => null,
                    'totalRecords' => null,
                ],
            ]),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.products-research-cjd'));

        $response->assertOk();
        $response->assertSee('Previous');
        $response->assertSee('Next');
        $response->assertDontSee('&hellip;', false);
    }
}
