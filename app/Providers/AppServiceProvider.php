<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\Cart\CartResolver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer(['partials.store-header', 'partials.store-mega-nav', 'partials.store-footer', 'store.home'], function ($view) {
            $view->with(
                'navCategories',
                Category::with(['subcategories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
            );
        });

        View::composer('partials.store-header', function ($view) {
            $view->with('cartItemCount', app(CartResolver::class)->currentCount(request()));
            $view->with('wishlistItemCount', auth()->user()?->wishlists()->count() ?? 0);
        });

        View::composer('components.product-card', function ($view) {
            $view->with('wishlistedIds', auth()->user()?->wishlists()->pluck('product_id') ?? collect());
        });
    }
}
