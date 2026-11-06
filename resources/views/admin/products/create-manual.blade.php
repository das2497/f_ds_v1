<x-admin-layout title="Add Product Manually">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.products') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0">Add Product Manually</h5>
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

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-center gap-2">
        <i class="bi bi-pencil-square fs-5 text-muted-slate"></i>
        <div class="small text-muted-slate">
            Not sourced from Printful or CJ Dropshipping — you supply every field, and this product is never auto-routed to a vendor for fulfillment. You're responsible for stocking and shipping it yourself.
        </div>
    </div>

    <form action="{{ route('admin.products.store-manual') }}" method="POST" enctype="multipart/form-data" id="manualProductForm">
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3"><i class="bi bi-card-text me-2 text-muted-slate"></i>Shop Listing Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Shop Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Shop Description</label>
                            <x-rich-text-editor name="description" id="descriptionField" :rows="5" placeholder="Write a customer-facing description...">{{ old('description') }}</x-rich-text-editor>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Shop Category</label>
                            <select name="category_id" id="shopCategory" class="form-select" required>
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" data-subcategories="{{ $category->subcategories->toJson() }}" {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Subcategory</label>
                            <select name="subcategory_id" id="shopSubcategory" class="form-select">
                                <option value="">Select category first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3"><i class="bi bi-currency-dollar me-2 text-muted-slate"></i>Pricing</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Retail Price</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Compare-at Price <span class="text-muted-slate">(optional)</span></label>
                            <input type="number" step="0.01" min="0" name="compare_at_price" class="form-control" value="{{ old('compare_at_price') }}">
                        </div>
                    </div>
                </div>

                <div class="table-admin p-4">
                    <h6 class="mb-3"><i class="bi bi-grid-3x3-gap me-2 text-muted-slate"></i>Variants &amp; SKU</h6>
                    <p class="small text-muted-slate">Add at least one variant. Your own cost price is what you paid for the stock — used only for your own margin tracking.</p>
                    <div class="table-responsive">
                        <table class="table table-admin align-middle mb-0">
                            <thead><tr><th>Image</th><th>Variant</th><th>Color</th><th>Size</th><th>Internal SKU</th><th>Cost</th><th>Weight (lb)</th><th></th></tr></thead>
                            <tbody id="variantRows"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="addVariantRow"><i class="bi bi-plus-lg"></i> Add Variant</button>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="position-sticky" style="top:1rem">
                    <div class="table-admin p-4 mb-3">
                        <h6 class="mb-3"><i class="bi bi-images me-2 text-muted-slate"></i>Product Images</h6>
                        <p class="small text-muted-slate">Upload at least one photo. The first image is the main photo shown on the shop.</p>
                        <label class="form-label small">Upload Images</label>
                        <input type="file" name="custom_images[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm" id="customImages">
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
        (function () {
            var categorySelect = document.getElementById('shopCategory');
            var subcategorySelect = document.getElementById('shopSubcategory');
            var preselectSubcategoryId = {{ old('subcategory_id') !== null ? (int) old('subcategory_id') : 'null' }};

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
            var customImages = document.getElementById('customImages');
            var counter = document.getElementById('imageCounter');

            function refresh() {
                var total = customImages.files.length;
                counter.textContent = total + ' image' + (total === 1 ? '' : 's') + ' selected';
            }

            customImages.addEventListener('change', refresh);
            refresh();
        })();

        (function () {
            var tbody = document.getElementById('variantRows');
            var addButton = document.getElementById('addVariantRow');
            var rowCount = 0;

            function addRow() {
                var i = rowCount++;
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' +
                        '<img src="https://placehold.co/48x48/F8FAFC/64748B?text=No+Image" class="js-variant-thumb" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--color-border)">' +
                        '<input type="file" name="variants[' + i + '][image]" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mt-1 js-variant-image-input" style="font-size:0.7rem">' +
                    '</td>' +
                    '<td><input type="text" name="variants[' + i + '][option_label]" class="form-control form-control-sm" placeholder="e.g. Medium / Blue" required></td>' +
                    '<td><input type="text" name="variants[' + i + '][color]" class="form-control form-control-sm" style="width:90px"></td>' +
                    '<td><input type="text" name="variants[' + i + '][size]" class="form-control form-control-sm" style="width:90px"></td>' +
                    '<td><input type="text" name="variants[' + i + '][sku]" class="form-control form-control-sm" placeholder="SHB-..." style="width:160px" required></td>' +
                    '<td><input type="number" step="0.01" min="0" name="variants[' + i + '][cost_price]" class="form-control form-control-sm" style="width:90px" required></td>' +
                    '<td><input type="number" step="0.01" min="0" name="variants[' + i + '][weight_lb]" class="form-control form-control-sm" style="width:80px"></td>' +
                    '<td><button type="button" class="btn-icon btn-icon-danger js-remove-variant" title="Remove"><i class="bi bi-trash3"></i></button></td>';

                tbody.appendChild(tr);

                tr.querySelector('.js-variant-image-input').addEventListener('change', function (e) {
                    var thumb = tr.querySelector('.js-variant-thumb');
                    if (e.target.files && e.target.files[0] && thumb) {
                        thumb.src = URL.createObjectURL(e.target.files[0]);
                    }
                });

                tr.querySelector('.js-remove-variant').addEventListener('click', function () {
                    if (tbody.querySelectorAll('tr').length > 1) {
                        tr.remove();
                    }
                });
            }

            addButton.addEventListener('click', addRow);

            addRow();
        })();
    </script>
</x-admin-layout>
