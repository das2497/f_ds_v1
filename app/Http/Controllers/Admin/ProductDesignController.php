<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\VendorProductCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductDesignController extends Controller
{
    private const PLACEMENTS = ['front', 'back'];

    /**
     * Uploads one artwork image for the canvas to reference by URL. Used
     * before an image is added to the canvas — without this, Fabric.js
     * would serialize the file's full base64 data straight into
     * `canvas_json` on every save (one row hit 550KB from a single photo,
     * which was enough to blow MySQL's sort buffer on any `ORDER BY` over
     * the whole table). Storing a small URL instead keeps `design_data`
     * tiny regardless of how many images a design uses.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $path = $request->file('image')->store('products/design-assets', 'public');

        // Root-relative on purpose, not Storage::url()'s APP_URL-based
        // absolute URL: the canvas loads this into an <img> and then reads
        // pixels back out of it via toDataURL() on save. The moment the
        // browser's own address bar host differs from APP_URL's host at
        // all — localhost vs 127.0.0.1 is the same machine but a different
        // origin as far as the browser is concerned — that draws a
        // cross-origin image onto the canvas, which taints it and makes
        // every future toDataURL()/toJSON() export throw silently. A
        // relative path always resolves against whatever host actually
        // served the page, so this can't happen regardless of which
        // hostname the admin is on.
        return response()->json(['url' => '/storage/'.$path]);
    }

    /**
     * Pre-publish draft save. A VendorProductCandidate's variants aren't
     * real ProductVariant rows yet (those only exist once the listing is
     * published — see ProductController::store()), so a draft design is
     * keyed by the vendor's own variant SKU instead of a variant id, and
     * handed off to the matching variant at publish time.
     */
    public function updateCandidate(Request $request, VendorProductCandidate $candidate): JsonResponse
    {
        abort_if($candidate->vendor !== 'printful', 403, 'The design canvas is only available for Printful products.');

        $vendorSku = $request->validate(['vendor_sku' => ['required', 'string']])['vendor_sku'];
        $design = $this->buildDesignEntry($request);

        $designData = $candidate->design_data ?? [];
        $designData[$vendorSku] ??= [];
        $designData[$vendorSku][$design['placement']] = $design['entry'];

        $candidate->update(['design_data' => $designData]);

        return response()->json(['status' => 'ok', 'placement' => $design['placement'], 'print_file_url' => $design['entry']['print_file_url']]);
    }

    /**
     * Post-publish save, directly against the variant a customer will
     * actually select and order.
     */
    public function updateVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        abort_if($variant->product->source_vendor !== 'printful', 403, 'The design canvas is only available for Printful products.');

        $design = $this->buildDesignEntry($request);

        $designData = $variant->design_data ?? [];
        $designData[$design['placement']] = $design['entry'];

        $variant->update(['design_data' => $designData]);

        return response()->json(['status' => 'ok', 'placement' => $design['placement'], 'print_file_url' => $design['entry']['print_file_url']]);
    }

    /**
     * @return array{placement: string, entry: array{print_file_url: string, canvas_json: mixed, width: int, height: int}}
     */
    private function buildDesignEntry(Request $request): array
    {
        $validated = $request->validate([
            'placement' => ['required', 'string', 'in:'.implode(',', self::PLACEMENTS)],
            // Capped well above any reasonable number of text/image-URL
            // layers — a payload this large almost certainly means an
            // image's data got embedded directly instead of uploaded and
            // referenced by URL (see uploadImage() above), which previously
            // bloated a single row past MySQL's sort buffer.
            'canvas_json' => ['required', 'string', 'max:200000'],
            'print_file_data' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
        ]);

        $binary = base64_decode(Str::after($validated['print_file_data'], 'data:image/png;base64,'), true);

        abort_if($binary === false, 422, 'The saved artwork could not be read — try saving the design again.');

        $path = 'products/designs/'.$validated['placement'].'-'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);

        return [
            'placement' => $validated['placement'],
            'entry' => [
                'print_file_url' => Storage::disk('public')->url($path),
                'canvas_json' => json_decode($validated['canvas_json'], true),
                'width' => $validated['width'],
                'height' => $validated['height'],
            ],
        ];
    }
}
