<?php

namespace Tests\Feature\Vendor;

use App\Services\Vendor\CJService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CJServiceProductFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_the_full_set_of_fields_cj_returns(): void
    {
        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'data' => ['accessToken' => 'test-token'],
            ]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => '000B9312',
                    'productNameEn' => 'Small trailer model',
                    'productSku' => 'CJJJJTJT05843',
                    'bigImage' => 'https://cf.cjdropshipping.com/main.jpg',
                    'productImageSet' => [
                        'https://cf.cjdropshipping.com/main.jpg',
                        'https://cf.cjdropshipping.com/angle2.jpg',
                    ],
                    'productWeight' => '1500.0',
                    'categoryName' => 'Home & Garden',
                    'sellPrice' => 58.09,
                    'suggestSellPrice' => '0.97-4.08',
                    'listedNum' => 392,
                    'materialNameEnSet' => ['', 'Metal'],
                    'addMarkStatus' => 1,
                    'supplierName' => 'Shenzhen TechCo',
                    'productKeyEn' => 'Color-Size',
                    'variants' => [
                        [
                            'vid' => 'v1',
                            'variantSku' => 'CJJJJTJT05843-Black',
                            'barcode' => '6973990191234',
                            'variantNameEn' => 'Small trailer model Black',
                            'variantKey' => 'Black-XXL',
                            'variantLength' => 300,
                            'variantWidth' => 200,
                            'variantHeight' => 100,
                            'variantWeight' => 1580.00,
                            'variantSellPrice' => 58.09,
                            'inventories' => [
                                ['countryCode' => 'US', 'totalInventory' => 1204],
                            ],
                        ],
                    ],
                ],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response([
                'data' => ['variantInventories' => []],
            ]),
        ]);

        config(['services.cj.api_key' => 'test-key']);

        $product = app(CJService::class)->getProduct('000B9312');

        $this->assertSame('CJJJJTJT05843', $product['vendor_sku']);
        $this->assertSame([
            'https://cf.cjdropshipping.com/main.jpg',
            'https://cf.cjdropshipping.com/angle2.jpg',
        ], $product['image_gallery']);
        $this->assertSame('https://cf.cjdropshipping.com/main.jpg', $product['preview_image_url']);
        $this->assertSame('0.97-4.08', $product['suggested_sell_price']);
        $this->assertSame(392, $product['listed_count']);
        $this->assertSame('Metal', $product['material']);
        $this->assertTrue($product['free_shipping']);
        $this->assertSame('Shenzhen TechCo', $product['supplier_name']);
        $this->assertEqualsWithDelta(3.31, $product['product_weight_lb'], 0.01);

        $variant = $product['variants'][0];
        $this->assertSame('Black', $variant['color']);
        $this->assertSame('XXL', $variant['size']);
        $this->assertSame('6973990191234', $variant['barcode']);
        $this->assertSame('11.81 x 7.87 x 3.94 in', $variant['dimensions_in']);
        $this->assertSame(1204, $variant['stock_qty']);
        $this->assertTrue($variant['in_stock']);
    }

    public function test_it_falls_back_gracefully_when_optional_fields_are_missing(): void
    {
        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'data' => ['accessToken' => 'test-token'],
            ]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => '123',
                    'productNameEn' => 'Bare Product',
                    'variants' => [
                        [
                            'vid' => 'v1',
                            'variantSku' => 'SKU-1',
                            'variantKey' => 'Red',
                            'variantSellPrice' => 4.5,
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

        $this->assertNull($product['vendor_sku']);
        $this->assertSame([], $product['image_gallery']);
        $this->assertNull($product['preview_image_url']);
        $this->assertNull($product['suggested_sell_price']);
        $this->assertNull($product['material']);
        $this->assertFalse($product['free_shipping']);

        $variant = $product['variants'][0];
        $this->assertSame('Red', $variant['color']);
        $this->assertNull($variant['size']);
        $this->assertNull($variant['dimensions_in']);
        $this->assertSame(0, $variant['stock_qty']);
        $this->assertFalse($variant['in_stock']);
    }
}
