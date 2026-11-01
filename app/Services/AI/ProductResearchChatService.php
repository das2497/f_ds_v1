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
 *
 * Every round is requested from Ollama with `stream: true` so the caller
 * (AiResearchController) can forward assistant text to the browser as it is
 * generated instead of waiting for the whole tool-calling loop to finish.
 */
class ProductResearchChatService
{
    private const BASE_URL = 'https://ollama.com/v1';

    private const MAX_TOOL_ROUNDS = 4;

    /**
     * @var array<string, array<string, mixed>> "vendor:external_id" => card, collected across every tool call in one stream() run
     */
    private array $cards = [];

    /**
     * @var array<int, array{tool: string, label: string, summary: string}> one entry per tool call executed in one stream() run — surfaced to the admin as "sources" for the reply
     */
    private array $sources = [];

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
     * Runs the tool-calling loop, streaming every round from Ollama and
     * forwarding assistant text to $onEvent('delta', ...) as it arrives.
     * Tool calls themselves are not streamed token-by-token (the admin
     * never reads raw tool-call JSON) — instead $onEvent fires a
     * 'tool_start' when a call is about to run and a 'tool_result' once it
     * finishes, which is also how the "sources" list returned at the end is
     * built.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  callable(string, array<string, mixed>): void  $onEvent
     * @return array{reply: string, cards: array<int, array<string, mixed>>, sources: array<int, array<string, mixed>>}
     */
    public function stream(array $history, callable $onEvent): array
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('Ollama Cloud');
        }

        $this->cards = [];
        $this->sources = [];

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$history,
        ];

        $fullContent = '';

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            [$assistantMessage, $roundContent] = $this->streamCompletion($messages, $onEvent);
            $fullContent .= $roundContent;

            $toolCalls = $assistantMessage['tool_calls'] ?? null;

            if (! $toolCalls) {
                return [
                    'reply' => $fullContent !== '' ? $fullContent : (string) ($assistantMessage['content'] ?? ''),
                    'cards' => array_values($this->cards),
                    'sources' => $this->sources,
                ];
            }

            $messages[] = $assistantMessage;

            foreach ($toolCalls as $toolCall) {
                $result = $this->runTool($toolCall, $onEvent);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result),
                ];
            }
        }

        return [
            'reply' => $fullContent !== '' ? $fullContent : "I had to stop after a few tool calls to keep this quick — ask me to narrow the search and I'll continue.",
            'cards' => array_values($this->cards),
            'sources' => $this->sources,
        ];
    }

    /**
     * @param  array{id: string, function: array{name: string, arguments: string}}  $toolCall
     * @param  callable(string, array<string, mixed>): void  $onEvent
     */
    private function runTool(array $toolCall, callable $onEvent): mixed
    {
        $name = $toolCall['function']['name'] ?? '';
        $label = $this->toolStartLabel($toolCall);

        $onEvent('tool_start', ['tool' => $name, 'label' => $label]);

        $result = $this->dispatchTool($toolCall);
        $summary = $this->toolResultSummary($name, $result);

        $this->sources[] = ['tool' => $name, 'label' => $label, 'summary' => $summary];
        $onEvent('tool_result', ['tool' => $name, 'summary' => $summary]);

        return $result;
    }

    /**
     * One streamed `/chat/completions` round. Reads the SSE body as it
     * arrives, forwards content deltas live, and accumulates tool-call
     * argument fragments (which Ollama, like OpenAI, sends split across
     * many chunks) into a complete tool-call array once the round ends.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  callable(string, array<string, mixed>): void  $onEvent
     * @return array{0: array<string, mixed>, 1: string} [assistant message shaped like a non-streamed response, the content streamed this round]
     */
    private function streamCompletion(array $messages, callable $onEvent): array
    {
        $response = Http::withToken($this->apiKey())
            ->baseUrl(self::BASE_URL)
            ->withOptions(['stream' => true])
            ->timeout(60)
            ->post('/chat/completions', [
                'model' => $this->model(),
                'messages' => $messages,
                'tools' => $this->toolSchema(),
                'tool_choice' => 'auto',
                'stream' => true,
            ]);

        if ($response->failed()) {
            throw new VendorApiException((string) ($response->json('error.message') ?: 'Ollama request failed.'));
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $content = '';
        $toolCalls = [];

        while (! $body->eof()) {
            $chunk = $body->read(2048);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($eventEnd = strpos($buffer, "\n\n")) !== false) {
                $eventBlock = substr($buffer, 0, $eventEnd);
                $buffer = substr($buffer, $eventEnd + 2);

                foreach (explode("\n", $eventBlock) as $line) {
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $payload = trim(substr($line, 5));

                    if ($payload === '' || $payload === '[DONE]') {
                        continue;
                    }

                    $delta = json_decode($payload, true);
                    $choiceDelta = $delta['choices'][0]['delta'] ?? null;

                    if (! $choiceDelta) {
                        continue;
                    }

                    if (filled($choiceDelta['content'] ?? null)) {
                        $content .= $choiceDelta['content'];
                        $onEvent('delta', ['text' => $choiceDelta['content']]);
                    }

                    foreach ($choiceDelta['tool_calls'] ?? [] as $toolCallDelta) {
                        $index = $toolCallDelta['index'] ?? 0;
                        $toolCalls[$index]['id'] = $toolCalls[$index]['id'] ?? ($toolCallDelta['id'] ?? "call_{$index}");
                        $toolCalls[$index]['type'] = 'function';
                        $toolCalls[$index]['function']['name'] =
                            ($toolCalls[$index]['function']['name'] ?? '').($toolCallDelta['function']['name'] ?? '');
                        $toolCalls[$index]['function']['arguments'] =
                            ($toolCalls[$index]['function']['arguments'] ?? '').($toolCallDelta['function']['arguments'] ?? '');
                    }
                }
            }
        }

        ksort($toolCalls);

        return [
            [
                'role' => 'assistant',
                'content' => $content !== '' ? $content : null,
                'tool_calls' => $toolCalls !== [] ? array_values($toolCalls) : null,
            ],
            $content,
        ];
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

    /**
     * A short, human-readable description of a tool call for the "Searching
     * CJ Dropshipping for..." status line shown while it runs.
     *
     * @param  array{id: string, function: array{name: string, arguments: string}}  $toolCall
     */
    private function toolStartLabel(array $toolCall): string
    {
        $name = $toolCall['function']['name'] ?? '';
        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
        $vendorLabel = ($args['vendor'] ?? null) === 'printful' ? 'Printful' : 'CJ Dropshipping';

        return match ($name) {
            'search_catalog' => "Searching {$vendorLabel} for \"".($args['query'] ?? '').'"...',
            'get_product' => "Looking up product details from {$vendorLabel}...",
            'check_demand' => 'Checking store demand for "'.($args['keyword'] ?? '').'"...',
            'score_product' => "Scoring this {$vendorLabel} product...",
            default => "Running {$name}...",
        };
    }

    /**
     * A short result summary for the "sources" list shown under the reply.
     */
    private function toolResultSummary(string $name, mixed $result): string
    {
        if (is_array($result) && isset($result['error'])) {
            return "Failed — {$result['error']}";
        }

        return match ($name) {
            'search_catalog' => count($result['items'] ?? []).' product(s) found'
                .(isset($result['total_records']) ? ' (of '.number_format($result['total_records']).' total)' : ''),
            'get_product' => 'Loaded "'.($result['title'] ?? 'product').'" details',
            'check_demand' => ($result['matching_product_views_30d'] ?? 0).' product view(s) and '
                .count($result['matching_searches'] ?? []).' matching search term(s) in the last 30 days',
            'score_product' => isset($result['score']) ? "Score: {$result['score']}/10 ({$result['verdict']})" : 'Scored',
            default => 'Done',
        };
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
            $this->addCard($vendor, 'search', $item);
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

        $this->addCard($vendor, 'detail', $product + ['variant_count' => count($product['variants'])]);

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
     * Checks real store demand for a keyword over the last 30 days, with
     * optional category/price narrowing so the model can answer questions
     * like "is there demand for yoga mats under $20" rather than just a raw
     * keyword match. Also always returns a top-categories breakdown so the
     * model can redirect the admin toward where real interest clusters even
     * when the exact keyword searched comes back thin.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function toolCheckDemand(array $args): array
    {
        $keyword = strtolower((string) ($args['keyword'] ?? ''));
        $category = $args['category'] ?? null;
        $minPrice = $args['min_price'] ?? null;
        $maxPrice = $args['max_price'] ?? null;

        if ($keyword === '') {
            return ['matching_searches' => [], 'matching_product_views_30d' => 0, 'top_categories_viewed' => []];
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

        $viewQuery = AnalyticsDemandLog::where('event_type', 'view_item')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('product', function ($q) use ($keyword, $category, $minPrice, $maxPrice) {
                $q->where('title', 'like', "%{$keyword}%");

                if (filled($category)) {
                    $q->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$category}%"));
                }

                if (is_numeric($minPrice)) {
                    $q->where('price', '>=', (float) $minPrice);
                }

                if (is_numeric($maxPrice)) {
                    $q->where('price', '<=', (float) $maxPrice);
                }
            });

        $viewCount = (clone $viewQuery)->count();

        $topCategories = AnalyticsDemandLog::where('event_type', 'view_item')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('product', fn ($q) => $q->where('title', 'like', "%{$keyword}%"))
            ->with('product.category')
            ->get()
            ->groupBy(fn ($log) => $log->product?->category?->name ?? 'Uncategorized')
            ->map->count()
            ->sortDesc()
            ->take(5)
            ->map(fn ($count, $name) => ['category' => $name, 'views' => $count])
            ->values();

        return [
            'matching_searches' => $matches,
            'matching_product_views_30d' => $viewCount,
            'top_categories_viewed' => $topCategories,
        ];
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

        $score = $this->scorer->scoreProduct($vendor, $externalId, [
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

        $this->addCard($vendor, 'detail', $product + [
            'variant_count' => count($product['variants']),
            'score' => $score['score'] ?? null,
            'verdict' => $score['verdict'] ?? null,
            'suggested_price' => $score['suggested_price'] ?? null,
        ]);

        return $score;
    }

    /**
     * @param  'search'|'detail'  $source  'search' = a bulk catalog search hit (sparse fields, many at once); 'detail' = a specific product the model looked up or scored (richer fields, usually 1-3 at once) — the client uses this to decide whether a side-by-side comparison table is worth showing.
     * @param  array<string, mixed>  $item
     */
    private function addCard(string $vendor, string $source, array $item): void
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
            'source' => $source,
            'suggested_sell_price' => $item['suggested_sell_price'] ?? $item['suggested_price'] ?? null,
            'free_shipping' => $item['free_shipping'] ?? null,
            'product_weight_lb' => $item['product_weight_lb'] ?? null,
            'score' => $item['score'] ?? null,
            'verdict' => $item['verdict'] ?? null,
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
                    'description' => 'Get full detail for one specific vendor product by its external id (from a prior search_catalog result). Call this (or score_product) before comparing two or more specific products side by side.',
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
                    'description' => 'Check whether real store visitors have searched for or viewed something matching this keyword in the last 30 days, optionally narrowed to a category or price range. Always call this before claiming a product is "in demand". Also returns which categories real views cluster in for this keyword, useful when the exact keyword comes back thin.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'description' => 'Optional: only count views of products in a category matching this name.'],
                            'min_price' => ['type' => 'number', 'description' => 'Optional: only count views of products priced at or above this.'],
                            'max_price' => ['type' => 'number', 'description' => 'Optional: only count views of products priced at or below this.'],
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
              ground that claim in real search/view data, not assumption. Use its
              category/min_price/max_price filters when the admin's question is
              scoped that way (e.g. "yoga mats under $20").
            - When the admin asks you to compare two or more specific products, call
              get_product or score_product on each one individually first — the UI
              renders a side-by-side comparison table from those, not from a
              search_catalog list.
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
