<?php

namespace App\Services\AI;

use App\Models\AnalyticsDemandLog;

/**
 * Real store-visitor demand for a keyword over the last 30 days — shared by
 * the AI Research Assistant's `check_demand` tool and the sourcing scorer's
 * "demand signal" input, so both features ground their claims in the same
 * query instead of two copies drifting apart.
 */
class DemandSignalService
{
    /**
     * @return array{matching_searches: array<int, array{term: string, searches: int}>, matching_product_views_30d: int, top_categories_viewed: array<int, array{category: string, views: int}>}
     */
    public function forKeyword(string $keyword, ?string $category = null, mixed $minPrice = null, mixed $maxPrice = null): array
    {
        $keyword = strtolower($keyword);

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
}
