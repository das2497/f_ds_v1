<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::with(['product', 'user'])
            ->when(
                $request->filled('q'),
                fn ($q) => $q->whereHas('product', fn ($p) => $p->where('title', 'like', '%'.$request->string('q').'%'))
            )
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Review::count(),
            'average' => (float) Review::avg('rating'),
        ];

        return view('admin.reviews.index', compact('reviews', 'stats'));
    }

    public function destroy(Review $review): RedirectResponse
    {
        AuditLog::record(
            'Review Deleted',
            "Product \"{$review->product->title}\": {$review->rating}★ review by {$review->user->name}"
        );

        $review->delete();

        return back()->with('status', 'Review deleted.');
    }
}
