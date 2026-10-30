<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CarouselSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CarouselSlideController extends Controller
{
    public function index(): View
    {
        $slides = CarouselSlide::orderBy('sort_order')->get();
        $carouselWidth = AppSetting::getInt('carousel.width');
        $carouselHeight = AppSetting::getInt('carousel.height');

        return view('admin.carousel.index', compact('slides', 'carouselWidth', 'carouselHeight'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'carousel_width' => ['nullable', 'integer', 'min:100', 'max:900'],
            'carousel_height' => ['nullable', 'integer', 'min:80', 'max:900'],
        ]);

        AppSetting::set('carousel.width', isset($validated['carousel_width']) ? (int) $validated['carousel_width'] : null);
        AppSetting::set('carousel.height', isset($validated['carousel_height']) ? (int) $validated['carousel_height'] : null);

        return back()->with('status', 'Carousel settings updated.');
    }

    public function bulkUpdateDimensions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_media_width' => ['required', 'integer', 'min:50', 'max:2000'],
            'bulk_media_height' => ['required', 'integer', 'min:50', 'max:2000'],
        ]);

        CarouselSlide::query()->update([
            'media_width' => $validated['bulk_media_width'],
            'media_height' => $validated['bulk_media_height'],
        ]);

        return back()->with('status', 'Width & height applied to every slide.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSlide($request, isUpdate: false);

        if ($request->hasFile('media')) {
            $validated['media_path'] = $request->file('media')->store('carousel', 'public');
        }

        unset($validated['media']);

        $validated['sort_order'] = (int) CarouselSlide::max('sort_order') + 1;

        CarouselSlide::create($validated);

        return back()->with('status', 'Slide added.');
    }

    public function update(Request $request, CarouselSlide $carousel): RedirectResponse
    {
        $validated = $this->validateSlide($request, isUpdate: true);

        if ($request->hasFile('media')) {
            if ($carousel->media_path) {
                Storage::disk('public')->delete($carousel->media_path);
            }
            $validated['media_path'] = $request->file('media')->store('carousel', 'public');
        }

        unset($validated['media']);

        $carousel->update($validated);

        return back()->with('status', 'Slide updated.');
    }

    public function destroy(CarouselSlide $carousel): RedirectResponse
    {
        if ($carousel->media_path) {
            Storage::disk('public')->delete($carousel->media_path);
        }

        $carousel->delete();

        return back()->with('status', 'Slide deleted.');
    }

    public function move(Request $request, CarouselSlide $carousel): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $neighbor = CarouselSlide::orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->where('sort_order', $direction === 'up' ? '<' : '>', $carousel->sort_order)
            ->first();

        if ($neighbor) {
            [$a, $b] = [$carousel->sort_order, $neighbor->sort_order];
            $carousel->update(['sort_order' => $b]);
            $neighbor->update(['sort_order' => $a]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSlide(Request $request, bool $isUpdate): array
    {
        return $request->validate([
            'media_type' => ['required', 'in:image,video'],
            'media' => [
                $isUpdate ? 'nullable' : 'required',
                'file',
                function ($attribute, $value, $fail) use ($request) {
                    if (! $value) {
                        return;
                    }
                    if ($request->input('media_type') === 'image' && ($value->getSize() > 3 * 1024 * 1024 || ! in_array($value->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'webp']))) {
                        $fail('Image must be JPG/PNG/WEBP and 3MB or smaller.');
                    }
                    if ($request->input('media_type') === 'video' && ($value->getSize() > 15 * 1024 * 1024 || $value->getClientOriginalExtension() !== 'mp4')) {
                        $fail('Video must be MP4 and 15MB or smaller.');
                    }
                },
            ],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'media_width' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'media_height' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:50'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
