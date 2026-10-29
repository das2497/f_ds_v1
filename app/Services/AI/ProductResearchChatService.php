<?php

namespace App\Services\AI;

use App\Exceptions\VendorApiException;
use App\Models\AnalyticsDemandLog;
use App\Models\AppSetting;
use App\Services\Vendor\CJService;
use App\Services\Vendor\PrintfulService;
use Illuminate\Support\Facades\Http;

/**
 * Conversational product-research assistant for the admin panel, backed by
 * the same Ollama Cloud chat-completions API as ProductScoringService, but
 * driving an OpenAI-style tool-calling loop instead of one JSON call — the
 * model decides when to search the CJ/Printful catalogs, pull a product's
 * detail, check real customer demand, or run the existing AI scorer, rather
 * than being handed pre-fetched data for a single product.
 */
class ProductResearchChatService
{
    private const BASE_URL = 'https://ollama.com/v1';

    private const MAX_TOOL_ROUNDS = 4;

    /**
     * @var array<string, array<string, mixed>> "vendor:external_id" => card, collected across every tool call in one respond() run
     */
    private array $cards = [];

    public function __construct(
        private readonly PrintfulService $printful,
        private readonly CJService $cj,
        private readonly ProductScoringService $scorer,
    ) {}

    public function configured(): bool
    {
        return filled($this->apiKey());
    }

    private function apiKey(): ?string
    {
        return AppSetting::get('ollama.api_key') ?: config('services.ollama.api_key');
    }

    private function model(): string
    {
        return AppSetting::get('ollama.model') ?: config('services.ollama.model');
    }

    /**
     * Ollama Cloud's account-wide credit balance (https://docs.ollama.com/api/balance)
     * — not a per-chat figure, so the UI fetches this on page load/refresh
     * rather than after every message. Shape varies by plan: current plans
     * return `included.balance_usd`/`allowance_usd`/`period`, older
     * session/weekly plans return `included.session`/`weekly` percentages.
     *
     * @return array<string, mixed>
     */
    public function balance(): array
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('Ollama Cloud');
        }

        $response = Http::withToken($this->apiKey())
            ->timeout(10)
            ->throw()
            ->get('https://ollama.com/api/balance');

        return $response->json();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, cards: array<int, array<string, mixed>>}
     */
    public function respond(array $history): array
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('Ollama Cloud');
        }

        $this->cards = [];

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$history,
        ];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $message = $this->completion($messages);
            $toolCalls = $message['tool_calls'] ?? null;

            if (! $toolCalls) {
                return [
                    'reply' => (string) ($message['content'] ?? ''),
                    'cards' => array_values($this->cards),
                ];
            }

            $messages[] = $message;

            foreach ($toolCalls as $toolCall) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($this->dispatchTool($toolCall)),
                ];
            }
        }

        return [
            'reply' => "I had to stop after a few tool calls to keep this quick — ask me to narrow the search and I'll continue.",
            'cards' => array_values($this->cards),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed> the raw assistant message from the API
     */
    private function completion(array $messages): array
    {
        $response = Http::withToken($this->apiKey())
            ->baseUrl(self::BASE_URL)
            ->timeout(45)
            ->throw()
            ->post('/chat/completions', [
                'model' => $this->model(),
                'messages' => $messages,
                'tools' => $this->toolSchema(),
                'tool_choice' => 'auto',
            ]);

        return (array) $response->json('choices.0.message', []);
    }

    /**
     * @param  array{id: string, function: array{name: string, arguments: string}}  $toolCall
     */
    private function dispatchTool(array $toolCall): mixed
    {
        $name = $toolCall['function']['name'] ?? '';
        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];

        try {
            return match ($name) {
                'search_catalog' => $this->toolSearchCatalog($args),
                'get_product' => $this->toolGetProduct($args),
                'check_demand' => $this->toolCheckDemand($args),
                'score_product' => $this->toolScoreProduct($args),
                default => ['error' => "Unknown tool: {$name}"],
            };
        } catch (VendorApiException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable) {
            return ['error' => 'Lookup failed.'];
        }
    }

    private function serviceFor(string $vendor): PrintfulService|CJService
    {
        return match ($vendor) {
            'printful' => $this->printful,
            'cj' => $this->cj,
            default => throw new VendorApiException("Unknown vendor: {$vendor}"),
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function toolSearchCatalog(array $args): array
    {
        $vendor = (string) ($args['vendor'] ?? '');
        $service = $this->serviceFor($vendor);

        if (! $service->configured()) {
            return ['error' => 'Not configured for this vendor yet.'];
        }

        $result = $service->searchCatalog(
            $args['query'] ?? null,
            $args['category'] ?? null,
            1,
            $args['sort'] ?? null,
            (bool) ($args['trending'] ?? false),
        );

        $items = array_slice($result['items'], 0, 8);

        foreach ($items as $item) {
            $this->addCard($vendor, $item);
        }

        return ['items' => $items, 'total_records' => $result['total_records']];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function toolGetProduct(array $args): array
    {
        $vendor = (string) ($args['vendor'] ?? '');
        $product = $this->serviceFor($vendor)->getProduct((string) ($args['external_id'] ?? ''));

        $this->addCard($vendor, $product + ['variant_count' => count($product['variants'])]);

        return [
            'vendor_external_id' => $product['vendor_external_id'],
            'title' => $product['title'],
            'category_hint' => $product['category_hint'] ?? null,
            'base_cost' => $product['base_cost'],
            'variant_count' => count($product['variants']),
            'listed_count' => $product['listed_count'] ?? null,
            'suggested_sell_price' => $product['suggested_sell_price'] ?? null,
            'free_shipping' => $product['free_shipping'] ?? false,
            'product_weight_lb' => $product['product_weight_lb'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function toolCheckDemand(array $args): array
    {
        $keyword = strtolower((string) ($args['keyword'] ?? ''));

        if ($keyword === '') {
            return ['matching_searches' => [], 'matching_product_views_30d' => 0];
        }

        $matches = AnalyticsDemandLog::where('event_type', 'search')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('search_term')
            ->selectRaw('search_term, COUNT(*) as searches')
            ->groupBy('search_term')
            ->get()
            ->filter(fn ($row) => str_contains(strtolower($row->search_term), $keyword)
                || str_contains($keyword, strtolower($row->search_term)))
            ->sortByDesc('searches')
            ->take(5)
            ->map(fn ($row) => ['term' => $row->search_term, 'searches' => (int) $row->searches])
            ->values();

        $viewCount = AnalyticsDemandLog::where('event_type', 'view_item')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('product', fn ($q) => $q->where('title', 'like', "%{$keyword}%"))
            ->count();

        return ['matching_searches' => $matches, 'matching_product_views_30d' => $viewCount];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function toolScoreProduct(array $args): array
    {
        $vendor = (string) ($args['vendor'] ?? '');
        $externalId = (string) ($args['external_id'] ?? '');
        $product = $this->serviceFor($vendor)->getProduct($externalId);

        $this->addCard($vendor, $product + ['variant_count' => count($product['variants'])]);

        return $this->scorer->scoreProduct($vendor, $externalId, [
            'title' => $product['title'],
            'category' => $product['category_hint'],
            'vendor' => $vendor,
            'base_cost' => $product['base_cost'],
            'variant_count' => count($product['variants']),
            'listed_count' => $product['listed_count'] ?? null,
            'suggested_sell_price' => $product['suggested_sell_price'] ?? null,
            'free_shipping' => $product['free_shipping'] ?? false,
            'weight_lb' => $product['product_weight_lb'] ?? null,
            'demand_keyword_match' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function addCard(string $vendor, array $item): void
    {
        $key = $vendor.':'.($item['vendor_external_id'] ?? '');

        $this->cards[$key] = [
            'vendor' => $vendor,
            'vendor_external_id' => $item['vendor_external_id'] ?? null,
            'title' => $item['title'] ?? 'Untitled Product',
            'base_cost' => $item['base_cost'] ?? null,
            'preview_image_url' => $item['preview_image_url'] ?? null,
            'category_hint' => $item['category_hint'] ?? null,
            'variant_count' => $item['variant_count'] ?? 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolSchema(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_catalog',
                    'description' => 'Search the Printful or CJ Dropshipping product catalog by keyword.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vendor' => ['type' => 'string', 'enum' => ['printful', 'cj']],
                            'query' => ['type' => 'string', 'description' => 'Search keywords.'],
                            'category' => ['type' => 'string', 'description' => 'Optional category filter.'],
                            'sort' => ['type' => 'string', 'enum' => ['price_asc', 'price_desc', 'popularity']],
                            'trending' => ['type' => 'boolean', 'description' => 'CJ only: limit to CJ-classified trending products.'],
                        ],
                        'required' => ['vendor', 'query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product',
                    'description' => 'Get full detail for one specific vendor product by its external id (from a prior search_catalog result).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vendor' => ['type' => 'string', 'enum' => ['printful', 'cj']],
                            'external_id' => ['type' => 'string'],
                        ],
                        'required' => ['vendor', 'external_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_demand',
                    'description' => 'Check whether real store visitors have searched for or viewed something matching this keyword in the last 30 days. Always call this before claiming a product is "in demand".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string'],
                        ],
                        'required' => ['keyword'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'score_product',
                    'description' => "Run the store's AI sourcing scorer (1-10, verdict, reasons, risks) on one specific vendor product.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vendor' => ['type' => 'string', 'enum' => ['printful', 'cj']],
                            'external_id' => ['type' => 'string'],
                        ],
                        'required' => ['vendor', 'external_id'],
                    ],
                ],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a professional dropshipping product-research assistant inside a
            store's admin panel. You help the admin source products by searching the
            Printful and CJ Dropshipping catalogs, checking real customer demand
            signals, and scoring candidates — never by guessing.

            Rules:
            - Always call a tool before making a factual claim about a specific
              product's cost, variants, availability, or competition. Never invent
              product data.
            - Never claim something is "trending" or "popular" from your own training
              knowledge. Only make trending/popularity claims that come directly from
              a tool result (search_catalog's trending results, or check_demand). If
              you have no tool evidence for demand, say so plainly instead of
              guessing.
            - Call check_demand before telling the admin a product is "in demand" —
              ground that claim in real search/view data, not assumption.
            - Flag anything that looks like an unlicensed copy of a trademarked
              character, logo, or brand name.
            - Keep replies concise and actionable — this is a busy admin, not a
              general chat. Prefer short paragraphs or bullet points over long prose.
            - Product cards are rendered separately by the UI for anything you look
              up — do not repeat full product listings as text, just reference them
              briefly and give your analysis.
            PROMPT;
    }
}
