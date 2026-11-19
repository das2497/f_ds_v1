<?php

use App\Http\Controllers\Api\AliExpressImportController;
use App\Http\Controllers\Api\SheetWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/sheet-sync/webhook', [SheetWebhookController::class, 'handle'])
    ->middleware('verify.webhook:sheet-sync')
    ->name('api.sheet-sync.webhook');

Route::post('/aliexpress/products', [AliExpressImportController::class, 'store'])
    ->middleware('verify.webhook:aliexpress')
    ->name('api.aliexpress.products');
