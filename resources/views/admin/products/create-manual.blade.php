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
                    <h6 class="mb-3"><i class="bi bi-link-45deg me-2 text-muted-slate"></i>Import from AliExpress <span class="text-muted-slate small fw-normal">(optional)</span></h6>
                    <p class="small text-muted-slate">Paste the AliExpress product link and fetch its title, description, photos and variant photos automatically — you still pick the category, price and SKUs yourself before publishing. Requires the Shobley Chrome extension to be installed and enabled.</p>
                    <div class="d-flex gap-2">
                        <input type="text" name="source_url" id="aliexpressLink" class="form-control" placeholder="https://www.aliexpress.com/item/....html" value="{{ old('source_url') }}">
                        <button type="button" class="btn btn-outline-secondary text-nowrap" id="aliexpressFetchBtn">Fetch from AliExpress</button>
                    </div>
                    <div class="small mt-2" id="aliexpressFetchStatus"></div>
                </div>

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
                    <p class="small text-muted-slate">Add at least one variant. Your own cost price is what you paid for the stock — used only for your own margin tracking. Leave "Price" blank to use the retail price above for that variant.</p>
                    <div class="table-responsive">
                        <table class="table table-admin align-middle mb-0">
                            <thead><tr><th>Image</th><th>Variant</th><th>Color</th><th>Size</th><th>Internal SKU</th><th>Cost</th><th>Price</th><th>Weight (lb)</th><th></th></tr></thead>
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
                        <div class="row g-2 mb-2" id="fetchedImagesRow"></div>
                        <label class="form-label small">Upload Images</label>
                        <input type="file" name="custom_images[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm js-crop-input" id="customImages" data-template-url="{{ $imageTemplateUrl }}">
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

    @include('admin.products._image-crop-modal')
    @vite(['resources/js/admin-image-crop.js'])

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
            var fetchedRow = document.getElementById('fetchedImagesRow');
            var tbody = document.getElementById('variantRows');
            var addVariantButton = document.getElementById('addVariantRow');
            var profitMarginPercent = {{ $profitMarginPercent }};
            var imageTemplateUrl = @json($imageTemplateUrl) || '';
            var titleField = document.querySelector('#manualProductForm [name="title"]');
            var descSurface = document.querySelector('[data-rte-surface]');
            var descSource = document.getElementById('descriptionField');
            var rowCount = 0;

            function refreshImageCounter() {
                var total = customImages.files.length + fetchedRow.querySelectorAll('.js-fetched-image-tile').length;
                counter.textContent = total + ' image' + (total === 1 ? '' : 's') + ' selected';
            }

            // Adds a tile for an image already fetched (and re-hosted) from
            // AliExpress — a hidden `images[]` input carries its URL
            // straight through to the server the same way edit.blade.php's
            // existing-image tiles do, alongside whatever gets uploaded.
            function addFetchedImage(url) {
                var tile = document.createElement('div');
                tile.className = 'col-6 js-fetched-image-tile';
                tile.innerHTML =
                    '<div class="cart-item__image position-relative" style="width:100%;height:90px">' +
                        '<img src="' + url + '" style="width:100%;height:100%;object-fit:cover;border-radius:6px">' +
                        '<input type="hidden" name="images[]" value="' + url + '">' +
                        '<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 js-remove-fetched-image" style="padding:0;width:22px;height:22px;line-height:1" aria-label="Remove this image">&times;</button>' +
                    '</div>';

                tile.querySelector('.js-remove-fetched-image').addEventListener('click', function () {
                    tile.remove();
                    refreshImageCounter();
                });

                fetchedRow.appendChild(tile);
                refreshImageCounter();
            }

            // `prefill`, when given, pre-fills the row from a scraped
            // AliExpress variant ({option_label, color, size, cost_price,
            // weight_lb, image}) instead of leaving it blank — the SKU is
            // always left for the admin to type themselves.
            function addVariantRow(prefill) {
                var i = rowCount++;
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' +
                        '<img src="https://placehold.co/48x48/F8FAFC/64748B?text=No+Image" class="js-variant-thumb" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--color-border)">' +
                        '<input type="file" name="variants[' + i + '][image]" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mt-1 js-variant-image-input js-crop-input" style="font-size:0.7rem" data-template-url="' + imageTemplateUrl + '">' +
                        '<input type="hidden" name="variants[' + i + '][vendor_image]" class="js-variant-vendor-image">' +
                    '</td>' +
                    '<td><input type="text" name="variants[' + i + '][option_label]" class="form-control form-control-sm js-variant-option-label" placeholder="e.g. Medium / Blue" required></td>' +
                    '<td><input type="text" name="variants[' + i + '][color]" class="form-control form-control-sm js-variant-color" style="width:90px"></td>' +
                    '<td><input type="text" name="variants[' + i + '][size]" class="form-control form-control-sm js-variant-size" style="width:90px"></td>' +
                    '<td><input type="text" name="variants[' + i + '][sku]" class="form-control form-control-sm" placeholder="SHB-..." style="width:160px" required></td>' +
                    '<td><input type="number" step="0.01" min="0" name="variants[' + i + '][cost_price]" class="form-control form-control-sm js-variant-cost-price" style="width:90px" required></td>' +
                    '<td><div class="input-group input-group-sm" style="width:110px"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" name="variants[' + i + '][price]" class="form-control form-control-sm js-variant-price"></div></td>' +
                    '<td><input type="number" step="0.01" min="0" name="variants[' + i + '][weight_lb]" class="form-control form-control-sm js-variant-weight" style="width:80px"></td>' +
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

                // Suggests this row's retail price from its own cost price
                // using the shop's configured profit margin — only while
                // the admin hasn't typed their own price for this row.
                var costInput = tr.querySelector('.js-variant-cost-price');
                var priceInput = tr.querySelector('.js-variant-price');

                function suggestPrice() {
                    var cost = parseFloat(costInput.value);
                    if (isNaN(cost) || cost < 0) {
                        return;
                    }
                    if (priceInput.value !== '' && priceInput.value !== priceInput.dataset.autoPrice) {
                        return;
                    }
                    var suggested = (cost * (1 + profitMarginPercent / 100)).toFixed(2);
                    priceInput.value = suggested;
                    priceInput.dataset.autoPrice = suggested;
                }

                costInput.addEventListener('input', suggestPrice);
                priceInput.addEventListener('input', function () {
                    if (priceInput.value !== priceInput.dataset.autoPrice) {
                        delete priceInput.dataset.autoPrice;
                    }
                });

                if (prefill) {
                    tr.querySelector('.js-variant-option-label').value = prefill.option_label || '';
                    tr.querySelector('.js-variant-color').value = prefill.color || '';
                    tr.querySelector('.js-variant-size').value = prefill.size || '';
                    tr.querySelector('.js-variant-cost-price').value = prefill.cost_price || '';
                    tr.querySelector('.js-variant-weight').value = prefill.weight_lb || '';
                    if (prefill.image) {
                        tr.querySelector('.js-variant-vendor-image').value = prefill.image;
                        tr.querySelector('.js-variant-thumb').src = prefill.image;
                    }
                    suggestPrice();
                }

                return tr;
            }

            customImages.addEventListener('change', refreshImageCounter);
            addVariantButton.addEventListener('click', function () { addVariantRow(); });

            refreshImageCounter();
            addVariantRow();

            // Fired by the Chrome extension's content script (admin.js)
            // once it's scraped the pasted AliExpress link and the server
            // has downloaded/re-hosted the images — see
            // AliExpressPrefillController.
            window.addEventListener('shobley-aliexpress-prefill', function (e) {
                var data = e.detail;
                if (!data) {
                    return;
                }

                if (data.title && titleField && ! titleField.value) {
                    titleField.value = data.title;
                }
                if (data.description && descSurface && descSource && ! descSource.value) {
                    descSurface.textContent = data.description;
                    descSource.value = data.description;
                }

                (data.images || []).forEach(addFetchedImage);

                if (data.variants && data.variants.length) {
                    // Drop any still-blank default row(s) before adding the
                    // fetched ones, so the admin isn't left with an empty
                    // required row alongside the real ones.
                    Array.from(tbody.querySelectorAll('tr')).forEach(function (tr) {
                        var label = tr.querySelector('.js-variant-option-label');
                        var cost = tr.querySelector('.js-variant-cost-price');
                        if (label && cost && ! label.value && ! cost.value) {
                            tr.remove();
                        }
                    });

                    data.variants.forEach(addVariantRow);
                }
            });
        })();
    </script>
</x-admin-layout>
