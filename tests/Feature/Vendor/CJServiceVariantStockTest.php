<?php

namespace Tests\Feature\Vendor;

use App\Services\Vendor\CJService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CJServiceVariantStockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduces what CJ's live API actually does for `/product/query`:
     * every variant's inline `inventories` field comes back `null`, even
     * though it's documented as present. Real stock only comes from the
     * dedicated `/product/stock/getInventoryByPid` call.
     */
    public function test_stock_comes_from_the_dedicated_inventory_endpoint_when_the_inline_field_is_null(): void
    {
        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'data' => ['accessToken' => 'test-token'],
            ]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => '123',
                    'productNameEn' => 'Test Product',
                    'variants' => [
                        ['vid' => 'v1', 'variantSku' => 'SKU-1', 'variantSellPrice' => 12.6, 'inventories' => null],
                        ['vid' => 'v2', 'variantSku' => 'SKU-2', 'variantSellPrice' => 12.6, 'inventories' => null],
                    ],
                ],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response([
                'data' => [
                    'variantInventories' => [
                        ['vid' => 'v1', 'inventory' => [['countryCode' => 'CN', 'totalInventory' => 11027]]],
                        ['vid' => 'v2', 'inventory' => [['countryCode' => 'CN', 'totalInventory' => 0]]],
                    ],
                ],
            ]),
        ]);

        config(['services.cj.api_key' => 'test-key']);

        $product = app(CJService::class)->getProduct('123');

        $this->assertTrue($product['variants'][0]['in_stock']);
        $this->assertSame(11027, $product['variants'][0]['stock_qty']);
        $this->assertFalse($product['variants'][1]['in_stock']);
        $this->assertSame(0, $product['variants'][1]['stock_qty']);
    }

    public function test_a_variant_falls_back_to_its_inline_inventories_field_when_the_dedicated_endpoint_has_no_data_for_it(): void
    {
        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'data' => ['accessToken' => 'test-token'],
            ]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => '123',
                    'productNameEn' => 'Test Product',
                    'variants' => [
                        [
                            'vid' => 'v1',
                            'variantSku' => 'SKU-1',
                            'variantSellPrice' => 9.99,
                            'inventories' => [
                                ['countryCode' => 'CN', 'totalInventory' => 0],
                                ['countryCode' => 'US', 'totalInventory' => 1204],
                            ],
                        ],
                        [
                            'vid' => 'v2',
                            'variantSku' => 'SKU-2',
                            'variantSellPrice' => 9.99,
                        ],
                    ],
                ],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response([
                'data' => ['variantInventories' => []],
            ]),
        ]);

        config(['services.cj.api_key' => 'test-key']);

        $product = app(CJService::class)->getProduct('123');

        $this->assertTrue($product['variants'][0]['in_stock'], 'Variant with inline US warehouse stock should be in stock.');
        $this->assertFalse($product['variants'][1]['in_stock'], 'Variant with no inventory data anywhere should be out of stock.');
    }

    public function test_it_degrades_gracefully_when_the_inventory_endpoint_itself_fails(): void
    {
        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'data' => ['accessToken' => 'test-token'],
            ]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => '123',
                    'productNameEn' => 'Test Product',
                    'variants' => [
                        ['vid' => 'v1', 'variantSku' => 'SKU-1', 'variantSellPrice' => 9.99, 'inventories' => null],
                    ],
                ],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        config(['services.cj.api_key' => 'test-key']);

        $product = app(CJService::class)->getProduct('123');

        $this->assertFalse($product['variants'][0]['in_stock']);
        $this->assertSame('Test Product', $product['title']);
    }
}
