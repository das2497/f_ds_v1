<?php

namespace App\Services\AI;

use App\Exceptions\VendorApiException;
use App\Models\AiProductScore;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * Scores a sourced product candidate using Ollama Cloud (an OpenAI-compatible
 * chat completions API — https://docs.ollama.com/api/openai-compatibility).
 *
 * The point isn't to predict trends the model has no live data for — it's to
 * turn cost/popularity/demand numbers a novice admin would otherwise have to
 * interpret themselves into one plain-language verdict + reasons, grounded
 * only in the data actually passed in (never the model's own "market
 * knowledge", which is stale by definition).
 */
class ProductScoringService
{
    private const BASE_URL = 'https://ollama.com/v1';

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
     * Scores a product, reusing a cached result when one exists for this
     * exact vendor/product and the scoring input hasn't changed since —
     * callers pay for a fresh Ollama Cloud call only on first score, on a
     * genuine input change (cost, listed_count, price, ...), or when the
     * caller explicitly asks for a refresh.
     *
     * @param  array{title: string, category: ?string, vendor: string, base_cost: float, variant_count: int, listed_count: ?int, suggested_sell_price: ?float, free_shipping: bool, weight_lb: ?float, demand_keyword_match: ?string}  $input
     * @return array{score: int, verdict: string, reasons: array<int, string>, risks: array<int, string>, suggested_price: ?float, cached: bool}
     */
    public function scoreProduct(string $vendor, string $externalId, array $input, bool $forceRefresh = false): array
    {
        $hash = md5(json_encode($input));

        $cached = AiProductScore::where('vendor', $vendor)
            ->where('vendor_external_id', $externalId)
            ->first();

        if ($cached && ! $forceRefresh && $cached->input_hash === $hash) {
            return [
                'score' => $cached->score,
                'verdict' => $cached->verdict,
                'reasons' => $cached->reasons,
                'risks' => $cached->risks,
                'suggested_price' => $cached->suggested_price === null ? null : (float) $cached->suggested_price,
                'cached' => true,
            ];
        }

        $result = $this->requestScore($input);

        AiProductScore::updateOrCreate(
            ['vendor' => $vendor, 'vendor_external_id' => $externalId],
            [...$result, 'input_hash' => $hash, 'scored_at' => now()]
        );

        return [...$result, 'cached' => false];
    }

    /**
     * @param  array{title: string, category: ?string, vendor: string, base_cost: float, variant_count: int, listed_count: ?int, suggested_sell_price: ?float, free_shipping: bool, weight_lb: ?float, demand_keyword_match: ?string}  $input
     * @return array{score: int, verdict: string, reasons: array<int, string>, risks: array<int, string>, suggested_price: ?float}
     */
    private function requestScore(array $input): array
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('Ollama Cloud');
        }

        $response = Http::withToken($this->apiKey())
            ->baseUrl(self::BASE_URL)
            ->timeout(30)
            ->throw()
            ->post('/chat/completions', [
                'model' => $this->model(),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => json_encode($input)],
                ],
            ]);

        $parsed = json_decode($this->stripMarkdownFence((string) $response->json('choices.0.message.content')), true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            throw new VendorApiException('Ollama Cloud returned a response that could not be parsed as a score.');
        }

        return [
            'score' => max(1, min(10, (int) $parsed['score'])),
            'verdict' => (string) ($parsed['verdict'] ?? ''),
            'reasons' => array_values(array_filter((array) ($parsed['reasons'] ?? []), 'is_string')),
            'risks' => array_values(array_filter((array) ($parsed['risks'] ?? []), 'is_string')),
            'suggested_price' => isset($parsed['suggested_price']) && is_numeric($parsed['suggested_price'])
                ? (float) $parsed['suggested_price']
                : null,
        ];
    }

    /**
     * `response_format: json_object` isn't strictly enforced by every
     * Ollama Cloud model — gemma4:31b, despite being told to reply with
     * only JSON, still wraps it in a ```json ... ``` markdown fence. Strip
     * that before decoding rather than failing the whole score.
     */
    private function stripMarkdownFence(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content);
            $content = preg_replace('/```\s*$/', '', (string) $content);
        }

        return trim((string) $content);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a dropshipping product-sourcing advisor helping a store owner
            with very little market experience decide whether to source a
            specific product. You will receive one product's data as JSON:
            title, category, vendor (printful or cj), base_cost (what the store
            pays per unit), variant_count, listed_count (how many other sellers
            already list this exact product on the vendor's marketplace — higher
            can mean proven demand but also more competition; null if unknown),
            suggested_sell_price (the vendor's own price suggestion, or null),
            free_shipping, weight_lb, and demand_keyword_match (a real search
            term recent visitors to this store searched for that matches this
            product, or null if there's no match).

            Judge the product on: likely sell-through (is this solving a real,
            specific need, or a generic/saturated item with no distinguishing
            angle?), margin potential (a 2-3x cost-to-price multiplier is normal
            for dropshipping — flag it if base_cost is high enough that even
            2.5x looks uncompetitive for the category), shipping practicality
            (lightweight and compact is lower-risk; heavy, fragile, or bulky
            items raise return and shipping-cost risk), and legal/platform risk
            (flag anything that looks like an unlicensed copy of a trademarked
            character, logo, or brand name).

            You have no access to live trend data — never claim something is
            "currently trending" or guess at real-time popularity. Base every
            claim only on the data given to you.

            Reply with ONLY a JSON object, no prose outside it, matching exactly
            this shape:
            {
              "score": <integer 1-10, 10 = excellent pick>,
              "verdict": "<one sentence, plain language, for someone new to ecommerce>",
              "reasons": ["<short reason>", "..."],
              "risks": ["<short risk flag>", "... (empty array if none)"],
              "suggested_price": <number, a realistic USD sell price, or null>
            }
            PROMPT;
    }
}
