<?php

use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Admin\AiResearchController;
use App\Http\Controllers\Admin\AnalyticsDemandController;
use App\Http\Controllers\Admin\AnalyticsUtmController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CarouselSlideController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HomepageSectionController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductDesignController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SelectedProductController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\SubcategoryController;
use App\Http\Controllers\Admin\TaxRateController as AdminTaxRateController;
use App\Http\Controllers\Admin\VendorResearchController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderCancelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/theme-check', function () {
    return view('theme-check');
});

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::put('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/{order:order_number}/pay', [CheckoutController::class, 'pay'])->name('checkout.pay');
Route::get('/order-success/{order:order_number}', [OrderController::class, 'success'])->name('order-success');

Route::get('/account/orders/{order:order_number}/cancel', [OrderCancelController::class, 'show'])->name('orders.cancel.show');
Route::post('/account/orders/{order:order_number}/cancel', [OrderCancelController::class, 'destroy'])->name('orders.cancel.destroy');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');

Route::view('/policies/privacy', 'store.policies.privacy')->name('policies.privacy');
Route::view('/policies/terms', 'store.policies.terms')->name('policies.terms');
Route::view('/policies/shipping-refund', 'store.policies.shipping-refund')->name('policies.shipping-refund');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Legacy Breeze scaffold URL — kept only so old bookmarks/links land on the
// visitor's real (themed) dashboard instead of the unstyled stub view.
Route::get('/dashboard', function () {
    return redirect()->route(auth()->user()->homeRouteName());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/account', [AccountDashboardController::class, 'index'])->name('account.dashboard');
    Route::get('/account/orders', [AccountOrderController::class, 'index'])->name('account.orders');
    Route::get('/account/orders/{order}', [AccountOrderController::class, 'show'])->name('account.orders.show');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin|super-admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/analytics-utm', [AnalyticsUtmController::class, 'index'])->name('analytics-utm');
    Route::get('/analytics-demand', [AnalyticsDemandController::class, 'index'])->name('analytics-demand');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('/subcategories', [SubcategoryController::class, 'store'])->name('subcategories.store');
    Route::put('/subcategories/{subcategory}', [SubcategoryController::class, 'update'])->name('subcategories.update');
    Route::delete('/subcategories/{subcategory}', [SubcategoryController::class, 'destroy'])->name('subcategories.destroy');

    Route::get('/carousel', [CarouselSlideController::class, 'index'])->name('carousel.index');
    Route::put('/carousel/settings', [CarouselSlideController::class, 'updateSettings'])->name('carousel.settings.update');
    Route::put('/carousel/bulk-dimensions', [CarouselSlideController::class, 'bulkUpdateDimensions'])->name('carousel.bulk-dimensions.update');
    Route::post('/carousel', [CarouselSlideController::class, 'store'])->name('carousel.store');
    Route::put('/carousel/{carousel}', [CarouselSlideController::class, 'update'])->name('carousel.update');
    Route::delete('/carousel/{carousel}', [CarouselSlideController::class, 'destroy'])->name('carousel.destroy');
    Route::post('/carousel/{carousel}/move', [CarouselSlideController::class, 'move'])->name('carousel.move');

    Route::post('/homepage-sections', [HomepageSectionController::class, 'store'])->name('homepage-sections.store');
    Route::put('/homepage-sections/{homepageSection}', [HomepageSectionController::class, 'update'])->name('homepage-sections.update');
    Route::delete('/homepage-sections/{homepageSection}', [HomepageSectionController::class, 'destroy'])->name('homepage-sections.destroy');
    Route::post('/homepage-sections/{homepageSection}/move', [HomepageSectionController::class, 'move'])->name('homepage-sections.move');
    Route::post('/homepage-sections/{homepageSection}/items', [HomepageSectionController::class, 'storeItem'])->name('homepage-sections.items.store');
    Route::put('/homepage-section-items/{homepageSectionItem}', [HomepageSectionController::class, 'updateItem'])->name('homepage-section-items.update');
    Route::delete('/homepage-section-items/{homepageSectionItem}', [HomepageSectionController::class, 'destroyItem'])->name('homepage-section-items.destroy');
    Route::post('/homepage-section-items/{homepageSectionItem}/move', [HomepageSectionController::class, 'moveItem'])->name('homepage-section-items.move');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products');
    Route::get('/products/create/{candidate}', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');

    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::post('/product-designs/upload-image', [ProductDesignController::class, 'uploadImage'])->name('product-designs.upload-image');
    Route::put('/product-candidates/{candidate}/design', [ProductDesignController::class, 'updateCandidate'])->name('candidates.design.update');
    Route::put('/product-variants/{variant}/design', [ProductDesignController::class, 'updateVariant'])->name('product-variants.design.update');

    Route::get('/products-research-printful', [VendorResearchController::class, 'index'])
        ->defaults('vendor', 'printful')->name('products-research-printful');
    Route::get('/products-research-printful/{externalId}', [VendorResearchController::class, 'show'])
        ->defaults('vendor', 'printful')->name('products-research-printful.show');
    Route::post('/products-research-printful/{externalId}/select', [VendorResearchController::class, 'select'])
        ->defaults('vendor', 'printful')->name('products-research-printful.select');
    Route::post('/products-research-printful/{externalId}/score', [VendorResearchController::class, 'score'])
        ->defaults('vendor', 'printful')->name('products-research-printful.score');

    Route::get('/products-research-cjd', [VendorResearchController::class, 'index'])
        ->defaults('vendor', 'cj')->name('products-research-cjd');
    Route::get('/products-research-cjd/{externalId}', [VendorResearchController::class, 'show'])
        ->defaults('vendor', 'cj')->name('products-research-cjd.show');
    Route::post('/products-research-cjd/{externalId}/select', [VendorResearchController::class, 'select'])
        ->defaults('vendor', 'cj')->name('products-research-cjd.select');
    Route::post('/products-research-cjd/{externalId}/score', [VendorResearchController::class, 'score'])
        ->defaults('vendor', 'cj')->name('products-research-cjd.score');

    Route::get('/ai-research', [AiResearchController::class, 'index'])->name('ai-research');
    Route::post('/ai-research/chat', [AiResearchController::class, 'chat'])
        ->middleware('throttle:20,1')->name('ai-research.chat');
    Route::get('/ai-research/balance', [AiResearchController::class, 'balance'])
        ->middleware('throttle:10,1')->name('ai-research.balance');

    Route::get('/products-selected', [SelectedProductController::class, 'index'])->name('products-selected');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/vendor-orders/{vendorOrder}/retry', [AdminOrderController::class, 'retryVendorOrder'])->name('vendor-orders.retry');
    Route::get('/order-retry', [AdminOrderController::class, 'retryQueue'])->name('order-retry');

    Route::get('/tax-rates', [AdminTaxRateController::class, 'index'])->name('tax-rates');
    Route::put('/tax-rates/{taxRate}', [AdminTaxRateController::class, 'update'])->name('tax-rates.update');

    Route::get('/refunds', [AdminRefundController::class, 'index'])->name('refunds');
    Route::post('/refunds', [AdminRefundController::class, 'store'])->name('refunds.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff');
    Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{user}', [AdminStaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{user}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');

    Route::get('/settings-gateways', [AdminSettingsController::class, 'index'])->name('settings-gateways');
    Route::put('/settings-gateways', [AdminSettingsController::class, 'update'])->name('settings-gateways.update');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs');

    Route::get('/maintenance', [AdminMaintenanceController::class, 'index'])->name('maintenance');
    Route::post('/maintenance/backup', [AdminMaintenanceController::class, 'backup'])->name('maintenance.backup');
    Route::get('/maintenance/backups/{filename}', [AdminMaintenanceController::class, 'downloadBackup'])->name('maintenance.backups.download');
    Route::post('/maintenance/failed-jobs/{uuid}/retry', [AdminMaintenanceController::class, 'retryFailedJob'])->name('maintenance.failed-jobs.retry');
});

Route::get('/clear', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');

    return 'Cache cleared successfully!';
});

Route::get('/link', function () {
    Artisan::call('storage:link');

    return 'Storage link created successfully!';
});

Route::get('/migrate', function () {
    Artisan::call('migrate --force');

    return 'Database tables migrated successfully!';
});

require __DIR__.'/auth.php';
