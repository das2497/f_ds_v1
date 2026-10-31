<x-admin-layout title="Reviews">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card"><div class="stat-card__label">Total Reviews</div><div class="stat-card__value">{{ $stats['total'] }}</div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card"><div class="stat-card__label">Average Rating</div><div class="stat-card__value">{{ $stats['total'] ? number_format($stats['average'], 1) : '—' }}</div></div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search by product..." style="width:220px">
            <select name="rating" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Ratings</option>
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected((string) request('rating') === (string) $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                @endfor
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reviews as $review)
                    <tr>
                        <td>
                            @if ($review->product)
                                <a href="{{ route('products.show', $review->product) }}" class="text-decoration-none" target="_blank">{{ $review->product->title }}</a>
                            @else
                                <span class="text-muted-slate small">Deleted product</span>
                            @endif
                        </td>
                        <td>{{ $review->user->name }}</td>
                        <td class="star-rating-display" aria-label="Rated {{ $review->rating }} out of 5">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
                            @endfor
                        </td>
                        <td class="small text-muted-slate" style="max-width:320px">{{ $review->body }}</td>
                        <td class="small text-muted-slate">{{ $review->created_at->format('M j, Y') }}</td>
                        <td>
                            <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" onsubmit="return confirm('Delete this review?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-slate py-4">No reviews yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $reviews->links() }}</div>
</x-admin-layout>
