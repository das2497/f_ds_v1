<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Images\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the "Fetch from AliExpress" button on the manual-product form —
 * unlike the one-click AliExpress extension import (Api\AliExpressImportController),
 * this never creates a Product itself. It just takes a scraped payload
 * (posted by the extension's content script, running on this very admin
 * page) and downloads/re-hosts the images, so the form's own JS can drop
 * them into the image gallery and variant rows for the admin to review,
 * edit, and publish themselves with their own category/pricing choices.
 */
class AliExpressPrefillController extends Controller
{
    public function __construct(private readonly ImageOptimizer $imageOptimizer) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'preview_image_url' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'variants' => ['nullable', 'array'],
            'variants.*.option_label' => ['nullable', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.size' => ['nullable', 'string', 'max:100'],
            'variants.*.weight_lb' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.image' => ['nullable', 'string'],
        ]);

        $images = collect($validated['images'] ?? [$validated['preview_image_url'] ?? null])
            ->filter()
            ->unique()
            ->map(fn ($url) => $this->imageOptimizer->storeFromUrlAsPublicUrl($url, 'products'))
            ->filter()
            ->values()
            ->all();

        $variants = collect($validated['variants'] ?? [])
            ->map(fn ($variant) => [
                'option_label' => $variant['option_label'] ?? 'Option',
                'color' => $variant['color'] ?? null,
                'size' => $variant['size'] ?? null,
                'weight_lb' => $variant['weight_lb'] ?? null,
                'cost_price' => $variant['cost_price'] ?? null,
                'image' => $this->imageOptimizer->storeFromUrlAsPublicUrl($variant['image'] ?? null, 'products/variants'),
            ])
            ->values()
            ->all();

        return response()->json([
            'title' => $validated['title'],
            'description' => trim(strip_tags((string) ($validated['description'] ?? ''))),
            'images' => $images,
            'variants' => $variants,
        ]);
    }
}
