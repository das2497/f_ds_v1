<?php

namespace Tests\Feature;

use App\Services\AI\ProductScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductScoringCacheTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOllamaResponse(int $score = 7): void
    {
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

    private function input(float $baseCost = 5.0): array
    {
        return [
            'title' => 'Adjustable Phone Stand',
            'category' => 'Phone Accessories',
            'vendor' => 'cj',
            'base_cost' => $baseCost,
            'variant_count' => 2,
            'listed_count' => 10,
            'suggested_sell_price' => 15.0,
            'free_shipping' => true,
            'weight_lb' => 0.3,
            'demand_keyword_match' => null,
        ];
    }

    public function test_it_reuses_a_cached_score_for_the_same_input(): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);
        $this->fakeOllamaResponse();

        $scorer = app(ProductScoringService::class);

        $first = $scorer->scoreProduct('cj', 'CJ123', $this->input());
        $second = $scorer->scoreProduct('cj', 'CJ123', $this->input());

        Http::assertSentCount(1);
        $this->assertFalse($first['cached']);
        $this->assertTrue($second['cached']);
        $this->assertSame($first['score'], $second['score']);
        $this->assertSame($first['verdict'], $second['verdict']);
    }

    public function test_it_rescoring_when_the_input_changes(): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);
        $this->fakeOllamaResponse();

        $scorer = app(ProductScoringService::class);

        $scorer->scoreProduct('cj', 'CJ123', $this->input(baseCost: 5.0));
        $result = $scorer->scoreProduct('cj', 'CJ123', $this->input(baseCost: 25.0));

        Http::assertSentCount(2);
        $this->assertFalse($result['cached']);
    }

    public function test_it_forces_a_fresh_call_when_requested(): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);
        $this->fakeOllamaResponse();

        $scorer = app(ProductScoringService::class);

        $scorer->scoreProduct('cj', 'CJ123', $this->input());
        $result = $scorer->scoreProduct('cj', 'CJ123', $this->input(), forceRefresh: true);

        Http::assertSentCount(2);
        $this->assertFalse($result['cached']);
    }
}
