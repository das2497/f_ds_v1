<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VendorApiException;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsDemandLog;
use App\Models\VendorProductCandidate;
use App\Services\AI\ProductScoringService;
use App\Services\Vendor\CJService;
use App\Services\Vendor\PrintfulService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorResearchController extends Controller
{
    private const PER_PAGE = 20;

    private const SORTS = ['price_asc', 'price_desc', 'popularity'];

    public function __construct(
        private readonly PrintfulService $printful,
        private readonly CJService $cj,
        private readonly ProductScoringService $scorer,
    ) {}

    public function index(Request $request, string $vendor): View
    {
        $service = $this->serviceFor($vendor);
        $query = $request->string('q')->toString() ?: null;
        $category = $request->string('category')->toString() ?: null;
        $sort = in_array($request->string('sort')->toString(), self::SORTS, true)
            ? $request->string('sort')->toString()
            : null;
        $page = max(1, $request->integer('page', 1));
        $trending = $request->boolean('trending');

        $results = [];
        $totalPages = null;
        $totalRecords = null;
        $error = null;

        if ($service->configured()) {
            try {
                $response = $service->searchCatalog($query ?: null, $category ?: null, $page, $sort, $trending);
                $results = $response['items'];
                $totalPages = $response['total_pages'];
                $totalRecords = $response['total_records'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = ucfirst($vendor === 'cj' ? 'CJ Dropshipping' : $vendor).' API credentials are not configured yet.';
        }

        $hasNextPage = $totalPages !== null ? $page < $totalPages : count($results) >= self::PER_PAGE;

        $selectedExternalIds = VendorProductCandidate::where('vendor', $vendor)
            ->whereIn('status', ['selected', 'listed'])
            ->pluck('vendor_external_id');

        return view('admin.vendor-research.index', [
            'vendor' => $vendor,
            'results' => $results,
            'error' => $error,
            'query' => $query,
            'category' => $category,
            'sort' => $sort,
            'trending' => $trending,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'hasNextPage' => $hasNextPage,
            'selectedExternalIds' => $selectedExternalIds,
            'selectedCount' => $selectedExternalIds->count(),
        ]);
    }

    public function show(string $externalId, string $vendor): View
    {
        $service = $this->serviceFor($vendor);
        $error = null;
        $product = null;

        try {
            $product = $service->getProduct($externalId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $isSelected = VendorProductCandidate::where('vendor', $vendor)
            ->where('vendor_external_id', $externalId)
            ->whereIn('status', ['selected', 'listed'])
            ->exists();

        return view('admin.vendor-research.show', [
            'vendor' => $vendor,
            'externalId' => $externalId,
            'product' => $product,
            'error' => $error,
            'isSelected' => $isSelected,
        ]);
    }

    public function select(Request $request, string $externalId, string $vendor): RedirectResponse
    {
        $service = $this->serviceFor($vendor);

        try {
            $product = $service->getProduct($externalId);
        } catch (VendorApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        VendorProductCandidate::updateOrCreate(
            ['vendor' => $vendor, 'vendor_external_id' => $externalId],
            [
                'raw_payload' => $product,
                'title' => $product['title'],
                'base_cost' => $product['base_cost'],
                'preview_image_url' => $product['preview_image_url'],
                'category_hint' => $product['category_hint'],
                'variant_count' => count($product['variants']),
                'status' => 'selected',
                'selected_by' => $request->user()->id,
                'selected_at' => now(),
            ]
        );

        return back()->with('status', 'Added to Selection.');
    }

    /**
     * On-demand AI score for one product — never triggered automatically,
     * so cost scales with how many products an admin actually investigates,
     * not with search-result volume.
     */
    public function score(Request $request, string $externalId, string $vendor): JsonResponse
    {
        $service = $this->serviceFor($vendor);

        try {
            $product = $service->getProduct($externalId);
        } catch (VendorApiException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        try {
            $result = $this->scorer->scoreProduct($vendor, $externalId, [
                'title' => $product['title'],
                'category' => $product['category_hint'],
                'vendor' => $vendor,
                'base_cost' => $product['base_cost'],
                'variant_count' => count($product['variants']),
                'listed_count' => $product['listed_count'] ?? null,
                'suggested_sell_price' => $product['suggested_sell_price'] ?? null,
                'free_shipping' => $product['free_shipping'] ?? false,
                'weight_lb' => $product['product_weight_lb'] ?? null,
                'demand_keyword_match' => $this->demandKeywordMatch($product['title']),
            ], $request->boolean('refresh'));
        } catch (VendorApiException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Cheap substring match against real visitor search terms (last 30
     * days) — not fuzzy/semantic, just enough to tell the scorer "a real
     * customer searched for something like this" versus guessing.
     */
    private function demandKeywordMatch(string $title): ?string
    {
        $titleLower = strtolower($title);

        return AnalyticsDemandLog::where('event_type', 'search')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('search_term')
            ->pluck('search_term')
            ->unique()
            ->first(fn ($term) => filled($term) && (
                str_contains($titleLower, strtolower($term)) || str_contains(strtolower($term), $titleLower)
            ));
    }

    private function serviceFor(string $vendor): PrintfulService|CJService
    {
        return match ($vendor) {
            'printful' => $this->printful,
            'cj' => $this->cj,
            default => abort(404),
        };
    }
}
