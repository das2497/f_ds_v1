<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomepageSectionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];

        $validated['sort_order'] = (int) HomepageSection::max('sort_order') + 1;

        HomepageSection::create($validated);

        return back()->with('status', 'Section added.');
    }

    public function update(Request $request, HomepageSection $homepageSection): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];

        $homepageSection->update($validated);

        return back()->with('status', 'Section updated.');
    }

    public function destroy(HomepageSection $homepageSection): RedirectResponse
    {
        foreach ($homepageSection->items as $item) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
        }

        $homepageSection->delete();

        return back()->with('status', 'Section deleted.');
    }

    public function move(Request $request, HomepageSection $homepageSection): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $neighbor = HomepageSection::orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->where('sort_order', $direction === 'up' ? '<' : '>', $homepageSection->sort_order)
            ->first();

        if ($neighbor) {
            [$a, $b] = [$homepageSection->sort_order, $neighbor->sort_order];
            $homepageSection->update(['sort_order' => $b]);
            $neighbor->update(['sort_order' => $a]);
        }

        return back();
    }

    public function storeItem(Request $request, HomepageSection $homepageSection): RedirectResponse
    {
        $validated = $this->validateItem($request, isUpdate: false);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('homepage-sections', 'public');
        }

        unset($validated['image']);

        $validated['homepage_section_id'] = $homepageSection->id;
        $validated['sort_order'] = (int) $homepageSection->items()->max('sort_order') + 1;

        HomepageSectionItem::create($validated);

        return back()->with('status', 'Card added.');
    }

    public function updateItem(Request $request, HomepageSectionItem $homepageSectionItem): RedirectResponse
    {
        $validated = $this->validateItem($request, isUpdate: true);

        if ($request->hasFile('image')) {
            if ($homepageSectionItem->image_path) {
                Storage::disk('public')->delete($homepageSectionItem->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('homepage-sections', 'public');
        }

        unset($validated['image']);

        $homepageSectionItem->update($validated);

        return back()->with('status', 'Card updated.');
    }

    public function destroyItem(HomepageSectionItem $homepageSectionItem): RedirectResponse
    {
        if ($homepageSectionItem->image_path) {
            Storage::disk('public')->delete($homepageSectionItem->image_path);
        }

        $homepageSectionItem->delete();

        return back()->with('status', 'Card deleted.');
    }

    public function moveItem(Request $request, HomepageSectionItem $homepageSectionItem): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $neighbor = HomepageSectionItem::where('homepage_section_id', $homepageSectionItem->homepage_section_id)
            ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->where('sort_order', $direction === 'up' ? '<' : '>', $homepageSectionItem->sort_order)
            ->first();

        if ($neighbor) {
            [$a, $b] = [$homepageSectionItem->sort_order, $neighbor->sort_order];
            $homepageSectionItem->update(['sort_order' => $b]);
            $neighbor->update(['sort_order' => $a]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, bool $isUpdate): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'image' => [
                $isUpdate ? 'nullable' : 'required',
                'file',
                'image',
                'max:3072',
                'mimes:jpg,jpeg,png,webp',
            ],
            'link_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
