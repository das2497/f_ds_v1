@php
    $vendorLabel = $candidate->vendor === 'cj' ? 'CJ Dropshipping' : 'Printful';
    $vendorColor = $candidate->vendor === 'cj' ? '#1E40AF' : '#6D28D9';
    $variants = $candidate->raw_payload['variants'] ?? [];
    $images = collect($variants)->pluck('image')->filter()->unique()->values();
    if ($candidate->preview_image_url) {
        $images = collect([$candidate->preview_image_url])->merge($images)->unique()->values();
    }
    $suggestedPrice = round($candidate->base_cost * 2.5, 2);
    $skuBase = 'SHB-'.strtoupper(\Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($candidate->title, 12, ''), '-'));
@endphp
<x-admin-layout title="Customize & List Product">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.products-selected') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0">Customize &amp; List Product</h5>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-center gap-2" style="background:#EDE9FE;border-color:#DDD6FE">
        <i class="bi bi-cloud-check fs-5" style="color:#6D28D9"></i>
        <div class="small" style="color:#5B21B6">
            Imported from <span class="badge rounded-pill" style="background:{{ $vendorColor }};color:#fff">{{ $vendorLabel }}</span>
            — Vendor Product #{{ $candidate->vendor_external_id }}. Vendor-provided fields (cost, weight, base images) are read-only; customize the fields below for your shop listing.
        </div>
    </div>

    <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" id="productForm">
        @csrf
        <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3"><i class="bi bi-card-text me-2 text-muted-slate"></i>Shop Listing Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Shop Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $candidate->title) }}" required>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label small mb-0">
                                    Shop Description
                                    @if ($suggestedDescription && old('description') === null)
                                        <span class="auto-fill-badge"><i class="bi bi-magic"></i> Auto-filled — review</span>
                                    @endif
                                </label>
                                <span class="small text-muted-slate" id="descriptionCounter"></span>
                            </div>
                            <x-rich-text-editor name="description" id="descriptionField" :rows="5" placeholder="Write a customer-facing description...">{{ old('description', $suggestedDescription) }}</x-rich-text-editor>
                        </div>
                        @php $selectedCategoryId = old('category_id', $suggestedCategoryId); @endphp
                        <div class="col-md-6">
                            <label class="form-label small">
                                Shop Category
                                @if ($suggestedCategoryId && old('category_id') === null)
                                    <span class="auto-fill-badge"><i class="bi bi-magic"></i> Suggested</span>
                                @endif
                            </label>
                            <select name="category_id" id="shopCategory" class="form-select" required>
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" data-subcategories="{{ $category->subcategories->toJson() }}" {{ (string) $selectedCategoryId === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Subcategory</label>
                            <select name="subcategory_id" id="shopSubcategory" class="form-select">
                                <option value="">Select category first</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Vendor Source</label>
                            <div class="readonly-chip">
                                <i class="bi bi-link-45deg text-muted-slate"></i>
                                <strong>{{ $vendorLabel }}</strong> — Product #{{ $candidate->vendor_external_id }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3"><i class="bi bi-currency-dollar me-2 text-muted-slate"></i>Pricing</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Vendor Base Cost</label>
                            <div class="readonly-chip">
                                <i class="bi bi-box-seam text-muted-slate"></i>
                                <strong>${{ number_format($candidate->base_cost, 2) }}</strong>
                            </div>
                            <div class="form-text">Pulled from {{ $vendorLabel }} — updates automatically.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Your Retail Price</label>
                            <input type="number" step="0.01" min="0" name="price" id="retailPriceField" class="form-control" value="{{ old('price', $suggestedPrice) }}" data-base-cost="{{ $candidate->base_cost }}" required>
                            <div class="margin-indicator" id="marginIndicator"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Compare-at Price <span class="text-muted-slate">(optional)</span></label>
                            <input type="number" step="0.01" min="0" name="compare_at_price" class="form-control" value="{{ old('compare_at_price') }}">
                        </div>
                    </div>
                </div>

                <div class="table-admin p-4">
                    <h6 class="mb-3"><i class="bi bi-grid-3x3-gap me-2 text-muted-slate"></i>Variants &amp; SKU Mapping</h6>
                    <p class="small text-muted-slate">Variants below were imported from the vendor. Pick only the ones you want to sell, edit their details, and optionally replace each variant's image.</p>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllVariants">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNoneVariants">Select None</button>
                        <span class="small fw-semibold text-muted-slate" id="variantCounter"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-admin align-middle mb-0">
                            <thead><tr><th>List</th><th>Image</th><th>Variant</th><th>Color</th><th>Size</th><th>Vendor SKU</th><th>Internal SKU</th><th>Weight (lb)</th><th>Stock</th>@if ($candidate->vendor === 'printful')<th>Design</th>@endif</tr></thead>
                            <tbody>
                                @forelse ($variants as $i => $variant)
                                    <tr>
                                        <td><input type="checkbox" name="variants[{{ $i }}][list]" value="1" checked class="js-variant-check"></td>
                                        <td>
                                            <img src="{{ $variant['image'] ?? $candidate->preview_image_url ?? 'https://placehold.co/48x48/F8FAFC/64748B?text=No+Image' }}" class="js-variant-thumb" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--color-border)" alt="{{ $variant['option_label'] }}">
                                            <input type="file" name="variants[{{ $i }}][image]" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mt-1 js-variant-image-input" style="font-size:0.7rem">
                                            <input type="hidden" name="variants[{{ $i }}][vendor_image]" value="{{ $variant['image'] ?? '' }}">
                                        </td>
                                        <td>
                                            <input type="text" name="variants[{{ $i }}][option_label]" class="form-control form-control-sm" value="{{ $variant['option_label'] }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="variants[{{ $i }}][color]" class="form-control form-control-sm" value="{{ $variant['color'] }}" style="width:90px">
                                        </td>
                                        <td>
                                            <input type="text" name="variants[{{ $i }}][size]" class="form-control form-control-sm" value="{{ $variant['size'] }}" style="width:90px">
                                        </td>
                                        <td class="small text-muted-slate">
                                            {{ $variant['vendor_sku'] }}
                                            <input type="hidden" name="variants[{{ $i }}][vendor_sku]" value="{{ $variant['vendor_sku'] }}">
                                            <input type="hidden" name="variants[{{ $i }}][cost_price]" value="{{ $variant['cost_price'] }}">
                                        </td>
                                        <td>
                                            <input type="text" name="variants[{{ $i }}][sku]" class="form-control form-control-sm" value="{{ $skuBase }}-{{ $i + 1 }}" required style="width:160px">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="variants[{{ $i }}][weight_lb]" class="form-control form-control-sm" value="{{ $variant['weight_lb'] }}" style="width:80px">
                                        </td>
                                        <td>
                                            @if (array_key_exists('in_stock', $variant))
                                                @if ($variant['in_stock'])
                                                    <span class="badge-status-paid">{{ ! empty($variant['stock_qty']) ? number_format($variant['stock_qty']) : 'In Stock' }}</span>
                                                @else
                                                    <span class="badge-status-hold">Out of Stock</span>
                                                @endif
                                            @else
                                                <span class="small text-muted-slate">—</span>
                                            @endif
                                        </td>
                                        @if ($candidate->vendor === 'printful')
                                            <td>
                                                @if (! empty($variant['vendor_sku']))
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#designModal-candidate-{{ $i }}"><i class="bi bi-brush"></i> Design</button>
                                                @else
                                                    <span class="small text-muted-slate">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $candidate->vendor === 'printful' ? 10 : 9 }}" class="text-center text-muted-slate small py-4">No variants were imported for this product.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($candidate->vendor === 'printful')
                    @foreach ($variants as $i => $variant)
                        @if (! empty($variant['vendor_sku']))
                            @include('admin.products._design-canvas', [
                                'modalId' => 'designModal-candidate-'.$i,
                                'variantLabel' => $variant['option_label'] ?? 'Variant',
                                'saveUrl' => route('admin.candidates.design.update', $candidate),
                                'placements' => $designPlacements,
                                'existingDesign' => $candidate->design_data[$variant['vendor_sku']] ?? [],
                                'referenceImage' => $variant['image'] ?? $candidate->preview_image_url,
                                'extraFields' => ['vendor_sku' => $variant['vendor_sku']],
                            ])
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="col-lg-4">
                <div class="position-sticky" style="top:1rem">
                    <div class="table-admin p-4 mb-3">
                        <h6 class="mb-3"><i class="bi bi-images me-2 text-muted-slate"></i>Product Images</h6>
                        <p class="small text-muted-slate">Select vendor images and/or upload your own. No limit on how many. Drag to reorder — the first image is the main photo shown on the shop. Click the &times; on an image to remove it from the list entirely.</p>
                        <div class="row g-2 mb-3" id="vendorImagesRow">
                            @forelse ($images as $image)
                                <div class="col-6 js-image-tile" draggable="true">
                                    <label class="image-picker-item d-block is-selected" style="width:100%;height:90px;cursor:grab">
                                        <img src="{{ $image }}" style="width:100%;height:100%;object-fit:cover" draggable="false">
                                        <input type="checkbox" name="images[]" value="{{ $image }}" checked class="image-picker-item__check js-image-input">
                                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 start-0 m-1 js-image-remove" style="padding:0;width:22px;height:22px;line-height:1" aria-label="Remove this image">&times;</button>
                                        <span class="image-picker-item__primary-tag js-main-badge" style="{{ $loop->first ? '' : 'display:none' }}">Primary</span>
                                    </label>
                                </div>
                            @empty
                                <div class="col-12 small text-muted-slate" id="noVendorImages">No vendor images available.</div>
                            @endforelse
                            @if ($images->isNotEmpty())
                                <div class="col-12 small text-muted-slate" id="noVendorImages" style="display:none">No vendor images selected — upload your own below.</div>
                            @endif
                        </div>
                        <label class="form-label small">Upload Custom Images</label>
                        <input type="file" name="custom_images[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm js-image-input" id="customImages">
                        <div class="form-text">JPG/PNG/WEBP, 5MB max per image.</div>
                        <div class="small mt-2" id="imageCounter"></div>
                    </div>

                    <div class="table-admin p-4 mb-3">
                        <h6 class="mb-3"><i class="bi bi-camera-video me-2 text-muted-slate"></i>Product Video <span class="text-muted-slate small">(optional)</span></h6>
                        <input type="file" name="video" accept="video/mp4" class="form-control form-control-sm">
                        <div class="form-text">MP4 only, 20MB max.</div>
                    </div>

                    <div class="table-admin p-4">
                        <h6 class="mb-3"><i class="bi bi-rocket-takeoff me-2 text-muted-slate"></i>Publish</h6>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeToggle" checked>
                            <label class="form-check-label small" for="activeToggle">Active (visible to customers)</label>
                        </div>
                        <button type="submit" class="btn btn-accent w-100">Publish to Store</button>
                        <div class="small text-muted-slate mt-2 text-center">You can edit every field again after publishing.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        @php $selectedSubcategoryId = old('subcategory_id', $suggestedSubcategoryId); @endphp
        (function () {
            var categorySelect = document.getElementById('shopCategory');
            var subcategorySelect = document.getElementById('shopSubcategory');
            var preselectSubcategoryId = {{ $selectedSubcategoryId !== null ? (int) $selectedSubcategoryId : 'null' }};

            function populate(preselectId) {
                var selected = categorySelect.options[categorySelect.selectedIndex];
                var subs = selected && selected.dataset.subcategories ? JSON.parse(selected.dataset.subcategories) : [];
                subcategorySelect.innerHTML = subs.length
                    ? subs.map(function (s) {
                        var isSelected = preselectId !== null && preselectId !== undefined && String(s.id) === String(preselectId);
                        return '<option value="' + s.id + '"' + (isSelected ? ' selected' : '') + '>' + s.name + '</option>';
                    }).join('')
                    : '<option value="">No subcategories</option>';
            }

            categorySelect.addEventListener('change', function () { populate(); });
            populate(preselectSubcategoryId);
        })();

        (function () {
            var row = document.getElementById('vendorImagesRow');
            var counter = document.getElementById('imageCounter');
            var noImagesNotice = document.getElementById('noVendorImages');
            var inputs = document.querySelectorAll('.js-image-input');

            function countSelected() {
                var checked = document.querySelectorAll('#vendorImagesRow input[type="checkbox"]:checked').length;
                var uploaded = document.getElementById('customImages').files.length;
                return checked + uploaded;
            }

            // The first CHECKED vendor image submits first, so it's what
            // product-card.blade.php treats as the main image — the badge
            // tracks whichever tile that actually is after a check/drag.
            function updateMainBadge() {
                var firstChecked = null;
                row.querySelectorAll('.js-image-tile').forEach(function (tile) {
                    var badge = tile.querySelector('.js-main-badge');
                    var checked = tile.querySelector('.js-image-input').checked;
                    if (checked && ! firstChecked) firstChecked = tile;
                    if (badge) badge.style.display = 'none';
                });
                if (firstChecked) {
                    var badge = firstChecked.querySelector('.js-main-badge');
                    if (badge) badge.style.display = '';
                }
            }

            function refresh() {
                var total = countSelected();
                counter.textContent = total + ' image' + (total === 1 ? '' : 's') + ' selected';

                document.querySelectorAll('.image-picker-item').forEach(function (item) {
                    var check = item.querySelector('.image-picker-item__check');
                    item.classList.toggle('is-selected', !!(check && check.checked));
                });

                if (noImagesNotice) {
                    noImagesNotice.style.display = row.querySelectorAll('.js-image-tile').length ? 'none' : '';
                }

                updateMainBadge();
            }

            inputs.forEach(function (input) {
                input.addEventListener('change', refresh);
            });

            // A remove button sits inside the <label> so it can float over
            // the same tile as the checkbox — without stopping the click
            // here first, the browser's native label-click-toggles-checkbox
            // behavior would also fire and re-tick the box we're deleting.
            row.addEventListener('click', function (e) {
                var removeBtn = e.target.closest('.js-image-remove');
                if (!removeBtn) return;
                e.preventDefault();
                e.stopPropagation();
                var tile = removeBtn.closest('.js-image-tile');
                if (tile) tile.remove();
                refresh();
            });

            var dragged = null;
            row.addEventListener('dragstart', function (e) {
                dragged = e.target.closest('.js-image-tile');
                if (!dragged) return;
                e.dataTransfer.effectAllowed = 'move';
                dragged.classList.add('is-dragging');
            });
            row.addEventListener('dragover', function (e) {
                if (!dragged) return;
                e.preventDefault();
                var target = e.target.closest('.js-image-tile');
                if (!target || target === dragged) return;
                var rect = target.getBoundingClientRect();
                var insertAfter = (e.clientX - rect.left) / rect.width > 0.5;
                row.insertBefore(dragged, insertAfter ? target.nextSibling : target);
            });
            row.addEventListener('dragend', function () {
                if (dragged) dragged.classList.remove('is-dragging');
                dragged = null;
                refresh();
            });

            refresh();
        })();

        (function () {
            var textarea = document.getElementById('descriptionField');
            var counter = document.getElementById('descriptionCounter');

            // Counts visible characters, not HTML markup — decoupled from
            // the rich-text editor's own init timing by stripping tags
            // straight from the textarea's current value each time.
            function visibleLength(html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                return tmp.textContent.length;
            }

            function refresh() {
                counter.textContent = visibleLength(textarea.value) + ' characters';
            }

            textarea.addEventListener('input', refresh);
            refresh();
        })();

        (function () {
            var priceField = document.getElementById('retailPriceField');
            var indicator = document.getElementById('marginIndicator');
            var baseCost = parseFloat(priceField.dataset.baseCost) || 0;

            function refresh() {
                var price = parseFloat(priceField.value);

                if (isNaN(price) || baseCost <= 0) {
                    indicator.textContent = '';
                    indicator.className = 'margin-indicator';
                    return;
                }

                var profit = price - baseCost;
                var markupPct = (profit / baseCost) * 100;

                if (profit < 0) {
                    indicator.textContent = 'Below vendor cost — you will lose $' + Math.abs(profit).toFixed(2) + ' per unit.';
                    indicator.className = 'margin-indicator is-negative';
                } else {
                    indicator.textContent = 'Profit $' + profit.toFixed(2) + ' per unit (' + markupPct.toFixed(0) + '% markup over vendor cost).';
                    indicator.className = 'margin-indicator is-positive';
                }
            }

            priceField.addEventListener('input', refresh);
            refresh();
        })();

        (function () {
            var checkboxes = document.querySelectorAll('.js-variant-check');
            var counter = document.getElementById('variantCounter');
            var selectAllBtn = document.getElementById('selectAllVariants');
            var selectNoneBtn = document.getElementById('selectNoneVariants');

            function refreshCounter() {
                var checked = document.querySelectorAll('.js-variant-check:checked').length;
                counter.textContent = checked + ' / ' + checkboxes.length + ' variants selected';
            }

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', refreshCounter);
            });

            selectAllBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { cb.checked = true; });
                refreshCounter();
            });
            selectNoneBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { cb.checked = false; });
                refreshCounter();
            });

            refreshCounter();
        })();

        (function () {
            document.querySelectorAll('.js-variant-image-input').forEach(function (input) {
                input.addEventListener('change', function () {
                    var thumb = input.closest('td').querySelector('.js-variant-thumb');
                    if (input.files && input.files[0] && thumb) {
                        thumb.src = URL.createObjectURL(input.files[0]);
                    }
                });
            });
        })();
    </script>
</x-admin-layout>
