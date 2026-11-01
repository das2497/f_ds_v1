<?php

namespace Tests\Feature\Admin;

use App\Models\AiProductScore;
use App\Models\AnalyticsDemandLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorResearchScoresTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function fakeOllamaScore(int $score = 7): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => $score,
                            'verdict' => 'Decent pick.',
                            'reasons' => ['Good margin'],
                            'risks' => [],
                            'suggested_price' => 19.99,
                        ]),
                    ],
                ]],
            ]),
        ]);
    }

    public function test_the_bulk_scores_endpoint_returns_only_cached_scores_for_the_requested_ids(): void
    {
        $admin = $this->admin();

        AiProductScore::create([
            'vendor' => 'cj', 'vendor_external_id' => 'CJ1', 'score' => 8,
            'verdict' => 'Great pick.', 'reasons' => ['a'], 'risks' => [],
            'input_hash' => 'x', 'scored_at' => now(),
        ]);
        AiProductScore::create([
            'vendor' => 'cj', 'vendor_external_id' => 'CJ2', 'score' => 3,
            'verdict' => 'Risky.', 'reasons' => [], 'risks' => ['Low margin'],
            'input_hash' => 'y', 'scored_at' => now(),
        ]);
        // A printful score with the same external id must never leak into a cj query.
        AiProductScore::create([
            'vendor' => 'printful', 'vendor_external_id' => 'CJ1', 'score' => 1,
            'verdict' => 'Different vendor.', 'reasons' => [], 'risks' => [],
            'input_hash' => 'z', 'scored_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('admin.products-research-cjd.scores').'?ids[]=CJ1&ids[]=CJ2&ids[]=CJ3'
        );

        $response->assertOk();
        $response->assertJsonPath('scores.CJ1.score', 8);
        $response->assertJsonPath('scores.CJ2.score', 3);
        $response->assertJsonMissingPath('scores.CJ3');
    }

    public function test_the_bulk_scores_endpoint_requires_ids(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('admin.products-research-cjd.scores'));

        $response->assertStatus(422);
    }

    public function test_a_non_admin_cannot_access_the_bulk_scores_endpoint(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('admin.products-research-cjd.scores').'?ids[]=CJ1')
            ->assertForbidden();
    }

    public function test_scoring_a_product_includes_a_richer_demand_signal_than_a_bare_keyword_match(): void
    {
        $this->fakeOllamaScore();

        $category = Category::create(['name' => 'Fitness', 'slug' => 'fitness']);
        $viewedProduct = Product::create([
            'category_id' => $category->id,
            'title' => 'Adjustable Phone Stand',
            'slug' => 'adjustable-phone-stand',
            'price' => 15,
            'images' => [],
            'is_active' => true,
        ]);
        AnalyticsDemandLog::create(['event_type' => 'view_item', 'product_id' => $viewedProduct->id, 'session_id' => 'sess-1']);
        AnalyticsDemandLog::create(['event_type' => 'search', 'search_term' => 'phone stand', 'session_id' => 'sess-2']);

        config(['services.cj.api_key' => 'test-cj-key']);

        Http::fake([
            '*/authentication/getAccessToken' => Http::response(['data' => ['accessToken' => 'cj-token']]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => 'CJ999',
                    'productNameEn' => 'Adjustable Phone Stand',
                    'productSku' => 'CJSKU',
                    'sellPrice' => 4.5,
                    'variants' => [],
                ],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response(['data' => ['variantInventories' => []]]),
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 7, 'verdict' => 'Good.', 'reasons' => [], 'risks' => [], 'suggested_price' => 10,
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.products-research-cjd.score', 'CJ999'))
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'chat/completions')) {
                return true;
            }

            $sentInput = json_decode($request['messages'][1]['content'], true);

            return isset($sentInput['demand_signal'])
                && $sentInput['demand_signal']['matching_product_views_30d'] === 1
                && ! array_key_exists('demand_keyword_match', $sentInput);
        });
    }

    public function test_the_score_response_reports_when_it_was_scored(): void
    {
        $admin = $this->admin();

        $cached = AiProductScore::create([
            'vendor' => 'cj', 'vendor_external_id' => 'CJ123', 'score' => 7,
            'verdict' => 'Decent.', 'reasons' => [], 'risks' => [],
            'input_hash' => md5(json_encode([
                'title' => 'Adjustable Phone Stand', 'category' => null, 'vendor' => 'cj', 'base_cost' => 4.5,
                'variant_count' => 0, 'listed_count' => null, 'suggested_sell_price' => null, 'free_shipping' => false,
                'weight_lb' => null, 'demand_signal' => ['matching_searches' => [], 'matching_product_views_30d' => 0, 'top_categories_viewed' => []],
            ])),
            'scored_at' => now()->subDays(5),
        ]);

        config(['services.cj.api_key' => 'test-cj-key']);

        Http::fake([
            '*/authentication/getAccessToken' => Http::response(['data' => ['accessToken' => 'cj-token']]),
            '*/product/query*' => Http::response([
                'data' => ['pid' => 'CJ123', 'productNameEn' => 'Adjustable Phone Stand', 'productSku' => 'X', 'sellPrice' => 4.5, 'variants' => []],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response(['data' => ['variantInventories' => []]]),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.products-research-cjd.score', 'CJ123'));

        $response->assertOk()->assertJsonPath('cached', true);
        $this->assertNotNull($response->json('scored_at'));
        $this->assertTrue($cached->scored_at->equalTo($response->json('scored_at')));
    }
}
