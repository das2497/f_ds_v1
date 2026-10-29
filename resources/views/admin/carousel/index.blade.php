<x-admin-layout title="Homepage Carousel">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-start gap-2">
        <i class="bi bi-info-circle text-accent"></i>
        <div class="small text-muted-slate">
            All slides display at the <strong>same fixed size</strong> on the homepage (recommended <strong>1600 &times; 533px</strong>, 21:7 ratio — mobile crops to 4:3). Upload an <strong>image</strong> (JPG/PNG, max 3 MB) or a <strong>video</strong> (MP4, max 15 MB) — whichever you upload fills the same frame automatically, so slides never jump in size.
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#slideModal"><i class="bi bi-plus-lg"></i> Add Slide</button>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th style="width:40px"></th><th>Preview</th><th>Type</th><th>Headline</th><th>CTA</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($slides as $slide)
                    <tr>
                        <td class="text-muted-slate">
                            <form action="{{ route('admin.carousel.move', $slide) }}" method="POST">
                                @csrf
                                <button type="submit" name="direction" value="up" class="btn btn-sm btn-link p-0 d-block text-muted-slate"><i class="bi bi-chevron-up"></i></button>
                                <button type="submit" name="direction" value="down" class="btn btn-sm btn-link p-0 d-block text-muted-slate"><i class="bi bi-chevron-down"></i></button>
                            </form>
                        </td>
                        <td>
                            @if ($slide->media_path)
                                @if ($slide->media_type === 'video')
                                    <video src="{{ asset('storage/'.$slide->media_path) }}" style="width:96px;height:48px;object-fit:cover" muted></video>
                                @else
                                    <img src="{{ asset('storage/'.$slide->media_path) }}" style="width:96px;height:48px;object-fit:cover;border-radius:6px">
                                @endif
                            @else
                                <div class="ph-icon-wrap rounded-3" style="width:96px;height:48px"><i class="bi {{ $slide->media_type === 'video' ? 'bi-play-circle' : 'bi-image' }}" style="font-size:1.4rem"></i></div>
                            @endif
                        </td>
                        <td>
                            @if ($slide->media_type === 'video')
                                <span class="badge rounded-pill" style="background:#FEF3C7;color:#92400E"><i class="bi bi-play-circle me-1"></i>Video</span>
                            @else
                                <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF"><i class="bi bi-image me-1"></i>Image</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold small">{{ $slide->headline }}</div>
                            <div class="small text-muted-slate">{{ $slide->eyebrow_text }}</div>
                        </td>
                        <td class="small">{{ $slide->cta_label }} &rarr; {{ $slide->cta_link }}</td>
                        <td>
                            @if ($slide->is_active)
                                <span class="badge-status-paid">Active</span>
                            @else
                                <span class="badge-status-hold">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="#" class="small text-accent text-decoration-none me-2" data-bs-toggle="modal" data-bs-target="#editSlideModal{{ $slide->id }}">Edit</a>
                            <form action="{{ route('admin.carousel.destroy', $slide) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this slide?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="small text-danger text-decoration-none border-0 bg-transparent p-0">Delete</button>
                            </form>
                        </td>
                    </tr>

                    <div class="modal fade" id="editSlideModal{{ $slide->id }}" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form action="{{ route('admin.carousel.update', $slide) }}" method="POST" enctype="multipart/form-data">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Carousel Slide</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small">Media Type</label>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="media_type" value="image" id="typeImage{{ $slide->id }}" @checked($slide->media_type === 'image')>
                                                        <label class="form-check-label small" for="typeImage{{ $slide->id }}"><i class="bi bi-image me-1"></i>Image</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="media_type" value="video" id="typeVideo{{ $slide->id }}" @checked($slide->media_type === 'video')>
                                                        <label class="form-check-label small" for="typeVideo{{ $slide->id }}"><i class="bi bi-play-circle me-1"></i>Video</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small">Replace File <span class="text-muted-slate">(optional — leave empty to keep current)</span></label>
                                                <input type="file" name="media" class="form-control" accept="image/*,video/mp4">
                                            </div>
                                            <div class="col-12"><hr class="my-1"></div>
                                            <div class="col-12">
                                                <label class="form-label small">Eyebrow Text</label>
                                                <input type="text" name="eyebrow_text" class="form-control" value="{{ $slide->eyebrow_text }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small">Headline</label>
                                                <input type="text" name="headline" class="form-control" value="{{ $slide->headline }}" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small">Subtext</label>
                                                <textarea name="subtext" class="form-control" rows="2">{{ $slide->subtext }}</textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">CTA Button Label</label>
                                                <input type="text" name="cta_label" class="form-control" value="{{ $slide->cta_label }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">CTA Link</label>
                                                <input type="text" name="cta_link" class="form-control" value="{{ $slide->cta_link }}">
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="slideActive{{ $slide->id }}" @checked($slide->is_active)>
                                                    <label class="form-check-label small" for="slideActive{{ $slide->id }}">Active (visible on homepage)</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-accent">Save Slide</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-slate py-4">No slides yet — add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add Slide Modal -->
    <div class="modal fade" id="slideModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('admin.carousel.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Carousel Slide</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small">Media Type</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="media_type" value="image" id="typeImage" checked>
                                        <label class="form-check-label small" for="typeImage"><i class="bi bi-image me-1"></i>Image</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="media_type" value="video" id="typeVideo">
                                        <label class="form-check-label small" for="typeVideo"><i class="bi bi-play-circle me-1"></i>Video</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Upload File <span class="text-muted-slate">(1600&times;533px recommended)</span></label>
                                <input type="file" name="media" class="form-control" accept="image/*,video/mp4" required>
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-12">
                                <label class="form-label small">Eyebrow Text <span class="text-muted-slate">(optional)</span></label>
                                <input type="text" name="eyebrow_text" class="form-control" placeholder="e.g. SHOP SMART • LIVE BETTER">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Headline</label>
                                <input type="text" name="headline" class="form-control" placeholder="e.g. Quality Products for a Smarter You." required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Subtext <span class="text-muted-slate">(optional)</span></label>
                                <textarea name="subtext" class="form-control" rows="2" placeholder="Short supporting line..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">CTA Button Label</label>
                                <input type="text" name="cta_label" class="form-control" placeholder="Shop Now">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">CTA Link</label>
                                <input type="text" name="cta_link" class="form-control" placeholder="/products">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="slideActiveNew" checked>
                                    <label class="form-check-label small" for="slideActiveNew">Active (visible on homepage)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-accent">Save Slide</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
