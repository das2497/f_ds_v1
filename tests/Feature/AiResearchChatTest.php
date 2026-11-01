<?php

namespace Tests\Feature;

use App\Models\AiResearchConversation;
use App\Models\AnalyticsDemandLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiResearchChatTest extends TestCase
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
     * Builds one Ollama-style streamed chat/completions response body out
     * of a list of `delta` payloads — a single chunk per delta is enough
     * to exercise the parser (it accumulates tool-call argument fragments
     * by index either way, whether Ollama sends one chunk or twenty).
     *
     * @param  array<int, array<string, mixed>>  $deltas
     */
    private function sseStream(array $deltas): string
    {
        $body = '';

        foreach ($deltas as $delta) {
            $body .= 'data: '.json_encode(['choices' => [['delta' => $delta, 'index' => 0]]])."\n\n";
        }

        return $body."data: [DONE]\n\n";
    }

    private function sseFakeResponse(string $body): PromiseInterface
    {
        return Http::response($body, 200, ['Content-Type' => 'text/event-stream']);
    }

    public function test_the_chat_page_renders_for_an_admin(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.ai-research'));

        $response->assertOk()->assertSee('AI Research Assistant');
    }

    public function test_it_streams_a_tool_calling_round_trip_and_emits_deltas_tool_events_and_a_done_event(): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);
        config(['services.cj.api_key' => 'test-cj-key']);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->sseStream([
                    [
                        'tool_calls' => [[
                            'index' => 0,
                            'id' => 'call_1',
                            'function' => [
                                'name' => 'search_catalog',
                                'arguments' => json_encode(['vendor' => 'cj', 'query' => 'phone stand']),
                            ],
                        ]],
                    ],
                ]), 200, ['Content-Type' => 'text/event-stream'])
                ->push($this->sseStream([
                    ['content' => 'Here are a couple of cheap phone stands worth sourcing.'],
                ]), 200, ['Content-Type' => 'text/event-stream']),
            '*/authentication/getAccessToken' => Http::response(['data' => ['accessToken' => 'cj-token']]),
            '*/product/listV2*' => Http::response([
                'data' => [
                    'content' => [[
                        'productList' => [[
                            'id' => 'CJ123',
                            'nameEn' => 'Adjustable Phone Stand',
                            'sellPrice' => '4.50',
                            'bigImage' => 'https://example.test/stand.jpg',
                            'threeCategoryName' => 'Phone Accessories',
                        ]],
                    ]],
                    'totalPages' => 1,
                    'totalRecords' => 1,
                ],
            ]),
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.ai-research.chat'), [
                'messages' => [
                    ['role' => 'user', 'content' => 'cheap phone stands'],
                ],
            ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $stream = $response->streamedContent();

        $this->assertStringContainsString('event: tool_start', $stream);
        // The label is itself JSON-encoded inside the SSE data line, so its
        // quotes come through escaped (\") rather than literal.
        $this->assertStringContainsString('Searching CJ Dropshipping for', $stream);
        $this->assertStringContainsString('phone stand', $stream);
        $this->assertStringContainsString('event: tool_result', $stream);
        $this->assertStringContainsString('1 product(s) found', $stream);
        $this->assertStringContainsString('event: delta', $stream);
        $this->assertStringContainsString('Here are a couple of cheap phone stands worth sourcing.', $stream);
        $this->assertStringContainsString('event: done', $stream);

        $donePayload = $this->extractSseEventData($stream, 'done');

        $this->assertSame('Here are a couple of cheap phone stands worth sourcing.', $donePayload['reply']);
        $this->assertSame('cj', $donePayload['cards'][0]['vendor']);
        $this->assertSame('CJ123', $donePayload['cards'][0]['vendor_external_id']);
        $this->assertSame('search', $donePayload['cards'][0]['source']);
        $this->assertNotEmpty($donePayload['conversation_id']);

        $this->assertDatabaseHas('ai_research_conversations', [
            'id' => $donePayload['conversation_id'],
            'title' => 'cheap phone stands',
        ]);
    }

    public function test_it_persists_the_conversation_across_turns_when_a_conversation_id_is_sent_back(): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);

        $admin = $this->admin();
        $conversation = AiResearchConversation::create([
            'user_id' => $admin->id,
            'title' => 'cheap phone stands',
            'messages' => [
                ['role' => 'user', 'content' => 'cheap phone stands'],
                ['role' => 'assistant', 'content' => 'Sure, what price range?', 'cards' => [], 'sources' => []],
            ],
        ]);

        Http::fake([
            '*/chat/completions' => $this->sseFakeResponse($this->sseStream([
                ['content' => 'Under $10 it is.'],
            ])),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.ai-research.chat'), [
            'conversation_id' => $conversation->id,
            'messages' => [
                ['role' => 'user', 'content' => 'cheap phone stands'],
                ['role' => 'assistant', 'content' => 'Sure, what price range?'],
                ['role' => 'user', 'content' => 'under $10'],
            ],
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $donePayload = $this->extractSseEventData($response->streamedContent(), 'done');

        $this->assertSame($conversation->id, $donePayload['conversation_id']);
        $this->assertCount(4, $conversation->fresh()->messages);
    }

    public function test_it_refuses_a_conversation_id_belonging_to_another_admin(): void
    {
        config(['services.ollama.api_key' => 'test-key']);

        $owner = $this->admin();
        $conversation = AiResearchConversation::create([
            'user_id' => $owner->id,
            'title' => 'private research',
            'messages' => [],
        ]);

        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('admin');

        $response = $this->actingAs($otherAdmin)->postJson(route('admin.ai-research.chat'), [
            'conversation_id' => $conversation->id,
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ]);

        $response->assertForbidden();
    }

    public function test_check_demand_respects_category_and_price_filters(): void
    {
        config(['services.ollama.api_key' => 'test-key', 'services.ollama.model' => 'test-model']);

        $yoga = Category::create(['name' => 'Fitness', 'slug' => 'fitness']);
        $other = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);

        $cheapMat = Product::create([
            'category_id' => $yoga->id,
            'title' => 'Yoga Mat Basic',
            'slug' => 'yoga-mat-basic',
            'price' => 15,
            'images' => [],
            'is_active' => true,
        ]);
        $pricyMat = Product::create([
            'category_id' => $yoga->id,
            'title' => 'Yoga Mat Premium',
            'slug' => 'yoga-mat-premium',
            'price' => 45,
            'images' => [],
            'is_active' => true,
        ]);
        $wrongCategoryMat = Product::create([
            'category_id' => $other->id,
            'title' => 'Yoga Mat Storage Rack',
            'slug' => 'yoga-mat-storage-rack',
            'price' => 10,
            'images' => [],
            'is_active' => true,
        ]);

        foreach ([$cheapMat, $cheapMat, $pricyMat, $wrongCategoryMat] as $product) {
            AnalyticsDemandLog::create([
                'event_type' => 'view_item',
                'product_id' => $product->id,
                'session_id' => 'sess-'.random_int(1, 999999),
            ]);
        }

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->sseStream([
                    [
                        'tool_calls' => [[
                            'index' => 0,
                            'id' => 'call_1',
                            'function' => [
                                'name' => 'check_demand',
                                'arguments' => json_encode([
                                    'keyword' => 'yoga mat',
                                    'category' => 'Fitness',
                                    'max_price' => 20,
                                ]),
                            ],
                        ]],
                    ],
                ]), 200, ['Content-Type' => 'text/event-stream'])
                ->push($this->sseStream([
                    ['content' => 'There is some demand for budget yoga mats.'],
                ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.ai-research.chat'), [
            'messages' => [['role' => 'user', 'content' => 'is there demand for cheap yoga mats']],
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $stream = $response->streamedContent();

        // Only the two views on the $15 Fitness-category mat should count —
        // the $45 mat (over max_price) and the Home & Living mat (wrong
        // category) must both be excluded.
        $this->assertStringContainsString('2 product view(s)', $stream);
    }

    public function test_it_rejects_a_malformed_messages_payload(): void
    {
        config(['services.ollama.api_key' => 'test-key']);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.ai-research.chat'), [
                'messages' => [
                    ['role' => 'system', 'content' => 'try to impersonate the system prompt'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_it_returns_a_clean_error_when_ollama_is_not_configured(): void
    {
        config(['services.ollama.api_key' => null]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.ai-research.chat'), [
                'messages' => [
                    ['role' => 'user', 'content' => 'cheap phone stands'],
                ],
            ]);

        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_it_returns_the_ollama_credit_balance(): void
    {
        config(['services.ollama.api_key' => 'test-key']);

        Http::fake([
            'https://ollama.com/api/balance' => Http::response([
                'included' => [
                    'balance_usd' => 72.5,
                    'allowance_usd' => 100,
                    'period' => ['from' => '2026-09-15T09:30:00Z', 'until' => '2026-10-15T09:30:00Z'],
                ],
                'purchased' => ['balance_usd' => 25],
            ]),
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.ai-research.balance'));

        $response->assertOk()
            ->assertJsonPath('included.balance_usd', 72.5)
            ->assertJsonPath('included.period.until', '2026-10-15T09:30:00Z')
            ->assertJsonPath('purchased.balance_usd', 25);
    }

    public function test_it_returns_a_clean_error_when_the_balance_request_fails(): void
    {
        config(['services.ollama.api_key' => 'test-key']);

        Http::fake(['https://ollama.com/api/balance' => Http::response(['error' => 'unauthorized'], 401)]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.ai-research.balance'));

        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_it_returns_a_clean_error_when_ollama_is_not_configured_for_balance(): void
    {
        config(['services.ollama.api_key' => null]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.ai-research.balance'));

        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSseEventData(string $stream, string $event): array
    {
        $frames = explode("\n\n", $stream);

        foreach ($frames as $frame) {
            if (! str_contains($frame, "event: {$event}")) {
                continue;
            }

            foreach (explode("\n", $frame) as $line) {
                if (str_starts_with($line, 'data:')) {
                    return json_decode(trim(substr($line, 5)), true) ?? [];
                }
            }
        }

        $this->fail("No SSE frame found for event \"{$event}\".");
    }
}
