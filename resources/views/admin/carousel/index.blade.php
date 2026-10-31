<x-admin-layout title="Homepage Settings">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-start gap-2">
        <i class="bi bi-info-circle text-accent"></i>
        <div class="small text-muted-slate">
            Slides show on the homepage as <strong>Amazon-style category cards</strong> side by side. Pick a <strong>background color</strong>, set the <strong>image width &amp; height (px)</strong> you want it cropped to, and upload a <strong>product image</strong> (JPG/PNG/WEBP, max 3&nbsp;MB) or a <strong>video</strong> (MP4, max 15&nbsp;MB) — it fills that exact size, cropped to fit.
        </div>
    </div>

    <div class="card-surface p-3 mb-3">
        <form action="{{ route('admin.carousel.settings.update') }}" method="POST" class="row g-3 align-items-end">
            @csrf @method('PUT')
            <div class="col-auto">
                <label class="form-label small mb-1">Carousel Width (px)</label>
                <input type="number" name="carousel_width" class="form-control" style="width:140px" min="100" max="900" step="10" value="{{ $carouselWidth }}" placeholder="Auto">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Carousel Height (px)</label>
                <input type="number" name="carousel_height" class="form-control" style="width:140px" min="80" max="900" step="10" value="{{ $carouselHeight }}" placeholder="Auto">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-accent btn-sm">Save Size</button>
            </div>
            <div class="col-12">
                <div class="small text-muted-slate">Sets one fixed card size for every slide at once, overriding each slide's own width/height. Leave a field empty to size cards from each slide's own image width/height instead.</div>
            </div>
        </form>
    </div>

    <div class="card-surface p-3 mb-3">
        <form action="{{ route('admin.carousel.bulk-dimensions.update') }}" method="POST" class="row g-3 align-items-end"
              onsubmit="return confirm('Apply this width &amp; height to every slide? Each slide\'s own current width/height will be overwritten.');">
            @csrf @method('PUT')
            <div class="col-auto">
                <label class="form-label small mb-1">Width (px)</label>
                <input type="number" name="bulk_media_width" class="form-control" style="width:120px" min="50" max="2000" step="10" value="300" required>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Height (px)</label>
                <input type="number" name="bulk_media_height" class="form-control" style="width:120px" min="50" max="2000" step="10" value="300" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-accent btn-sm">Apply to All Slides</button>
            </div>
            <div class="col-12">
                <div class="small text-muted-slate">Overwrites the width &amp; height on every existing slide at once, instead of editing them one by one.</div>
            </div>
        </form>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#slideModal"><i class="bi bi-plus-lg"></i> Add Slide</button>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th style="width:40px"></th><th>Preview</th><th>Color</th><th>Type</th><th>Links to</th><th>Status</th><th></th></tr>
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
                            <div style="width:96px;height:48px;border-radius:6px;background:{{ $slide->background_color ?: '#DBEAFE' }};display:flex;align-items:center;justify-content:center;overflow:hidden">
                                @if ($slide->media_path)
                                    @if ($slide->media_type === 'video')
                                        <video src="{{ asset('storage/'.$slide->media_path) }}" style="width:100%;height:100%;object-fit:contain" muted></video>
                                    @else
                                        <img src="{{ asset('storage/'.$slide->media_path) }}" style="width:100%;height:100%;object-fit:contain">
                                    @endif
                                @else
                                    <i class="bi {{ $slide->media_type === 'video' ? 'bi-play-circle' : 'bi-image' }}" style="font-size:1.4rem;opacity:0.5"></i>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="d-inline-block rounded-circle" style="width:20px;height:20px;background:{{ $slide->background_color ?: '#DBEAFE' }};border:1px solid var(--color-border)" title="{{ $slide->background_color }}"></span>
                        </td>
                        <td>
                            @if ($slide->media_type === 'video')
                                <span class="badge rounded-pill" style="background:#FEF3C7;color:#92400E"><i class="bi bi-play-circle me-1"></i>Video</span>
                            @else
                                <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF"><i class="bi bi-image me-1"></i>Image</span>
                            @endif
                        </td>
                        <td class="small">{{ $slide->cta_link ?: '—' }}</td>
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
                                            <div class="col-md-6">
                                                <label class="form-label small">Card Background Color</label>
                                                <input type="color" name="background_color" class="form-control form-control-color" value="{{ $slide->background_color ?: '#DBEAFE' }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Image Width (px)</label>
                                                <input type="number" name="media_width" class="form-control" min="50" max="2000" step="10" value="{{ $slide->media_width ?? 300 }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Image Height (px)</label>
                                                <input type="number" name="media_height" class="form-control" min="50" max="2000" step="10" value="{{ $slide->media_height ?? 300 }}">
                                            </div>
                                            <div class="col-12"><hr class="my-1"></div>
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
                                <label class="form-label small">Upload File <span class="text-muted-slate">(cropped to the width/height set below)</span></label>
                                <input type="file" name="media" class="form-control" accept="image/*,video/mp4" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Card Background Color</label>
                                <input type="color" name="background_color" class="form-control form-control-color" value="#DBEAFE">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Image Width (px)</label>
                                <input type="number" name="media_width" class="form-control" min="50" max="2000" step="10" value="300">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Image Height (px)</label>
                                <input type="number" name="media_height" class="form-control" min="50" max="2000" step="10" value="300">
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
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

    <hr class="my-5">

    <h2 class="h5 mb-3">Homepage Card Sections</h2>

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-start gap-2">
        <i class="bi bi-info-circle text-accent"></i>
        <div class="small text-muted-slate">
            Groups of category cards shown side by side under the carousel (e.g. <strong>"Plug in with our electronics"</strong> with Headphones, Tablets, Gaming…). Each section has a <strong>title</strong>, an optional <strong>"view all" link</strong>, and its own <strong>cards</strong> (image + label + link).
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#sectionModal"><i class="bi bi-plus-lg"></i> Add Section</button>
    </div>

    @forelse ($sections as $section)
        <div class="card-surface p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="d-flex align-items-start gap-2">
                    <form action="{{ route('admin.homepage-sections.move', $section) }}" method="POST">
                        @csrf
                        <button type="submit" name="direction" value="up" class="btn btn-sm btn-link p-0 d-block text-muted-slate"><i class="bi bi-chevron-up"></i></button>
                        <button type="submit" name="direction" value="down" class="btn btn-sm btn-link p-0 d-block text-muted-slate"><i class="bi bi-chevron-down"></i></button>
                    </form>
                    <div>
                        <div class="fw-semibold">{{ $section->title }}</div>
                        <div class="small text-muted-slate">{{ $section->link_url ?: 'No "view all" link set' }}</div>
                        @if ($section->is_active)
                            <span class="badge-status-paid">Active</span>
                        @else
                            <span class="badge-status-hold">Inactive</span>
                        @endif
                    </div>
                </div>
                <div>
                    <a href="#" class="small text-accent text-decoration-none me-2" data-bs-toggle="modal" data-bs-target="#editSectionModal{{ $section->id }}">Edit</a>
                    <form action="{{ route('admin.homepage-sections.destroy', $section) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this section and all its cards?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="small text-danger text-decoration-none border-0 bg-transparent p-0">Delete</button>
                    </form>
                </div>
            </div>

            <div class="table-admin p-3 mb-3">
                <table class="table table-admin align-middle mb-0">
                    <thead>
                        <tr><th style="width:40px"></th><th>Image</th><th>Label</th><th>Links to</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($section->items as $item)
                            <tr>
                                <td class="text-muted-slate">
                                    <form action="{{ route('admin.homepage-section-items.move', $item) }}" method="POST">
                                        @csrf
                                        <button type="submit" name="direction" value="up" class="btn btn-sm btn-link p-0 d-block text-muted-slate"><i class="bi bi-chevron-up"></i></button>
                                        <button type="submit" name="direction" value="down" class="btn btn-sm btn-link p-0 d-block text-muted-slate"><i class="bi bi-chevron-down"></i></button>
                                    </form>
                                </td>
                                <td>
                                    <div style="width:56px;height:56px;border-radius:6px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;overflow:hidden">
                                        @if ($item->image_path)
                                            <img src="{{ asset('storage/'.$item->image_path) }}" style="width:100%;height:100%;object-fit:cover">
                                        @else
                                            <i class="bi bi-image" style="font-size:1.4rem;opacity:0.5"></i>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $item->label }}</td>
                                <td class="small">{{ $item->link_url ?: '—' }}</td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge-status-paid">Active</span>
                                    @else
                                        <span class="badge-status-hold">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="#" class="small text-accent text-decoration-none me-2" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item->id }}">Edit</a>
                                    <form action="{{ route('admin.homepage-section-items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this card?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="small text-danger text-decoration-none border-0 bg-transparent p-0">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('admin.homepage-section-items.update', $item) }}" method="POST" enctype="multipart/form-data">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Card</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label small">Label</label>
                                                        <input type="text" name="label" class="form-control" value="{{ $item->label }}" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">Replace Image <span class="text-muted-slate">(optional — leave empty to keep current)</span></label>
                                                        <input type="file" name="image" class="form-control" accept="image/*">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">Link URL</label>
                                                        <input type="text" name="link_url" class="form-control" value="{{ $item->link_url }}" placeholder="/products?cat=electronics">
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="itemActive{{ $item->id }}" @checked($item->is_active)>
                                                            <label class="form-check-label small" for="itemActive{{ $item->id }}">Active (visible on homepage)</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-accent">Save Card</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted-slate py-3">No cards yet — add one below.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <button class="btn btn-outline-accent btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal{{ $section->id }}"><i class="bi bi-plus-lg"></i> Add Card</button>
        </div>

        <!-- Edit Section Modal -->
        <div class="modal fade" id="editSectionModal{{ $section->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.homepage-sections.update', $section) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Section</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small">Title</label>
                                    <input type="text" name="title" class="form-control" value="{{ $section->title }}" required maxlength="100">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">"View All" Link</label>
                                    <input type="text" name="link_url" class="form-control" value="{{ $section->link_url }}" placeholder="/products?cat=electronics">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="sectionActive{{ $section->id }}" @checked($section->is_active)>
                                        <label class="form-check-label small" for="sectionActive{{ $section->id }}">Active (visible on homepage)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-accent">Save Section</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Card Modal -->
        <div class="modal fade" id="addItemModal{{ $section->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.homepage-sections.items.store', $section) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Card to "{{ $section->title }}"</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small">Label</label>
                                    <input type="text" name="label" class="form-control" placeholder="Headphones" required maxlength="60">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Image <span class="text-muted-slate">(JPG/PNG/WEBP, max 3&nbsp;MB)</span></label>
                                    <input type="file" name="image" class="form-control" accept="image/*" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Link URL</label>
                                    <input type="text" name="link_url" class="form-control" placeholder="/products?cat=electronics">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="itemActiveNew{{ $section->id }}" checked>
                                        <label class="form-check-label small" for="itemActiveNew{{ $section->id }}">Active (visible on homepage)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-accent">Save Card</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="card-surface p-4 text-center text-muted-slate mb-3">No sections yet — add one above.</div>
    @endforelse

    <!-- Add Section Modal -->
    <div class="modal fade" id="sectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.homepage-sections.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small">Title</label>
                                <input type="text" name="title" class="form-control" placeholder="Plug in with our electronics" required maxlength="100">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">"View All" Link</label>
                                <input type="text" name="link_url" class="form-control" placeholder="/products?cat=electronics">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="sectionActiveNew" checked>
                                    <label class="form-check-label small" for="sectionActiveNew">Active (visible on homepage)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-accent">Save Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
