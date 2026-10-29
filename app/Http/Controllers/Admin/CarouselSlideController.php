<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return view('admin.carousel.index', compact('slides'));
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
            'eyebrow_text' => ['nullable', 'string', 'max:100'],
            'headline' => ['required', 'string', 'max:255'],
            'subtext' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:50'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
