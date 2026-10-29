<?php

namespace Tests\Feature\Vendor;

use App\Services\Vendor\CJService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CJServiceDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProductResponse(string $description): void
    {
        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'data' => ['accessToken' => 'test-token'],
            ]),
            '*/product/query*' => Http::response([
                'data' => [
                    'pid' => '123',
                    'productNameEn' => 'Memory Foam Cooling Gel Swirl Infused Bed Topper',
                    'description' => $description,
                    'productImage' => ['https://example.test/img.jpg'],
                    'variants' => [],
                ],
            ]),
            '*/product/stock/getInventoryByPid*' => Http::response([
                'data' => ['variantInventories' => []],
            ]),
        ]);

        config(['services.cj.api_key' => 'test-key']);
    }

    public function test_it_splits_cj_description_into_a_clean_spec_table_and_sanitized_html(): void
    {
        $raw = '<p>specifications</p>'
            .'<p>Length * Width * Height: 8.00 x 8.00 x 20.00 inches Length * Width * Height (Package): 8.00 x 8.00 x 20.00 inches</p>'
            .'<p>Weight: 8.00 lb Weight: 8.00 lb</p>'
            .'<p>Product Code: ZHUMI-TP-HM-2T Specification: 2 Inches/Win</p>'
            .'<p> </p>'
            .'<p>specifications</p>'
            .'<p>Length * Width * Height: 9.00 x 9.00 x 20.00 inches Length * Width * Height (Package): 9.00 x 9.00 x 20.00 inches</p>'
            .'<p>Weight: 10.00 lb Weight: 10.00 lb</p>'
            .'<p>Product Code: ZHUMI-TP-HM-2F Specification: 2 Inches/Full</p>'
            .'<p> </p>'
            .'<p>Description:</p><br/></p>'
            .'<p><ul> <li>Superior Comfort - memory foam that conforms to your body.</li> <li>Breathable and cool all year round.</li></ul></p>';

        $this->fakeProductResponse($raw);

        $product = app(CJService::class)->getProduct('123');

        $this->assertCount(2, $product['specifications']);
        $this->assertSame([
            'code' => 'ZHUMI-TP-HM-2T',
            'spec' => '2 Inches/Win',
            'dimensions' => '8.00 x 8.00 x 20.00 inches',
            'package_dimensions' => '8.00 x 8.00 x 20.00 inches',
            'weight_lb' => '8.00 lb',
        ], $product['specifications'][0]);
        $this->assertSame('2 Inches/Full', $product['specifications'][1]['spec']);

        $this->assertStringNotContainsString('specifications</p>', $product['description']);
        $this->assertStringNotContainsString('Product Code', $product['description']);
        $this->assertStringContainsString('<ul>', $product['description']);
        $this->assertStringContainsString('Superior Comfort', $product['description']);
    }

    public function test_it_strips_unsafe_markup_from_the_description(): void
    {
        $raw = 'Description:<p onclick="alert(1)">Hi <script>alert(1)</script><img src=x onerror=alert(1)> there</p>';

        $this->fakeProductResponse($raw);

        $product = app(CJService::class)->getProduct('123');

        $this->assertSame('<p>Hi  there</p>', $product['description']);
    }

    public function test_it_falls_back_to_the_full_sanitized_html_when_no_description_marker_is_present(): void
    {
        $raw = '<p>Just a plain CJ description with no specifications block.</p>';

        $this->fakeProductResponse($raw);

        $product = app(CJService::class)->getProduct('123');

        $this->assertSame([], $product['specifications']);
        $this->assertSame($raw, $product['description']);
    }
}
