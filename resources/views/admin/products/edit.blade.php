@php
    $subcategories = $categories->firstWhere('id', $product->category_id)?->subcategories ?? collect();
@endphp
<x-admin-layout title="Edit Product">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.products') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0">Edit Product</h5>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data" id="productForm">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3">Shop Listing Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Shop Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $product->title) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Shop Description</label>
                            <x-rich-text-editor name="description" :rows="4">{{ old('description', $product->description) }}</x-rich-text-editor>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Shop Category</label>
                            <select name="category_id" id="shopCategory" class="form-select" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" data-subcategories="{{ $category->subcategories->toJson() }}" @selected($product->category_id === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Subcategory</label>
                            <select name="subcategory_id" id="shopSubcategory" class="form-select">
                                @foreach ($subcategories as $subcategory)
                                    <option value="{{ $subcategory->id }}" @selected($product->subcategory_id === $subcategory->id)>{{ $subcategory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3">Pricing</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Retail Price</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $product->price) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Compare-at Price <span class="text-muted-slate">(optional)</span></label>
                            <input type="number" step="0.01" min="0" name="compare_at_price" class="form-control" value="{{ old('compare_at_price', $product->compare_at_price) }}">
                        </div>
                    </div>
                </div>

                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3">Variants</h6>
                    <p class="small text-muted-slate">Edit details, replace images, or untick "Active" to stop selling a variant without deleting it.</p>
                    <div class="table-responsive">
                        <table class="table table-admin align-middle mb-0">
                            <thead><tr><th>Active</th><th>Image</th><th>Variant</th><th>Color</th><th>Size</th><th>Internal SKU</th><th>Weight (lb)</th><th>Cost</th></tr></thead>
                            <tbody>
                                @foreach ($product->variants as $variant)
                                    <tr>
                                        <td><input type="checkbox" name="existing_variants[{{ $variant->id }}][is_active]" value="1" @checked($variant->is_active)></td>
                                        <td>
                                            <img src="{{ $variant->image ?? 'https://placehold.co/48x48/F8FAFC/64748B?text=No+Image' }}" class="js-variant-thumb" style="width:44px;height:44px;object-fit:cover;border-radius:6px">
                                            <input type="file" name="existing_variants[{{ $variant->id }}][image]" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mt-1 js-variant-image-input" style="font-size:0.7rem">
                                        </td>
                                        <td>
                                            <input type="text" name="existing_variants[{{ $variant->id }}][option_label]" class="form-control form-control-sm" value="{{ $variant->option_label }}" required>
                                        </td>
                                        <td><input type="text" name="existing_variants[{{ $variant->id }}][color]" class="form-control form-control-sm" value="{{ $variant->color }}" style="width:90px"></td>
                                        <td><input type="text" name="existing_variants[{{ $variant->id }}][size]" class="form-control form-control-sm" value="{{ $variant->size }}" style="width:90px"></td>
                                        <td><input type="text" name="existing_variants[{{ $variant->id }}][sku]" class="form-control form-control-sm" value="{{ $variant->sku }}" style="width:160px" required></td>
                                        <td><input type="number" step="0.01" min="0" name="existing_variants[{{ $variant->id }}][weight_lb]" class="form-control form-control-sm" value="{{ $variant->weight_lb }}" style="width:80px"></td>
                                        <td class="small text-muted-slate">${{ number_format($variant->cost_price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($availableVendorVariants->isNotEmpty())
                    <div class="table-admin p-4">
                        <h6 class="mb-3">Add More Variants</h6>
                        <p class="small text-muted-slate">More variants are available from {{ $product->source_vendor === 'cj' ? 'CJ Dropshipping' : 'Printful' }} for this product. Tick the ones you want to start selling.</p>
                        <div class="table-responsive">
                            <table class="table table-admin align-middle mb-0">
                                <thead><tr><th>Add</th><th>Image</th><th>Variant</th><th>Color</th><th>Size</th><th>Vendor SKU</th><th>Internal SKU</th><th>Weight (lb)</th></tr></thead>
                                <tbody>
                                    @foreach ($availableVendorVariants as $i => $variant)
                                        <tr>
                                            <td><input type="checkbox" name="new_variants[{{ $i }}][add]" value="1"></td>
                                            <td>
                                                <img src="{{ $variant['image'] ?? 'https://placehold.co/48x48/F8FAFC/64748B?text=No+Image' }}" class="js-variant-thumb" style="width:44px;height:44px;object-fit:cover;border-radius:6px">
                                                <input type="file" name="new_variants[{{ $i }}][image]" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mt-1 js-variant-image-input" style="font-size:0.7rem">
                                                <input type="hidden" name="new_variants[{{ $i }}][vendor_image]" value="{{ $variant['image'] ?? '' }}">
                                            </td>
                                            <td><input type="text" name="new_variants[{{ $i }}][option_label]" class="form-control form-control-sm" value="{{ $variant['option_label'] }}"></td>
                                            <td><input type="text" name="new_variants[{{ $i }}][color]" class="form-control form-control-sm" value="{{ $variant['color'] }}" style="width:90px"></td>
                                            <td><input type="text" name="new_variants[{{ $i }}][size]" class="form-control form-control-sm" value="{{ $variant['size'] }}" style="width:90px"></td>
                                            <td class="small text-muted-slate">
                                                {{ $variant['vendor_sku'] }}
                                                <input type="hidden" name="new_variants[{{ $i }}][vendor_sku]" value="{{ $variant['vendor_sku'] }}">
                                                <input type="hidden" name="new_variants[{{ $i }}][cost_price]" value="{{ $variant['cost_price'] }}">
                                            </td>
                                            <td><input type="text" name="new_variants[{{ $i }}][sku]" class="form-control form-control-sm" placeholder="SHB-..." style="width:160px"></td>
                                            <td><input type="number" step="0.01" min="0" name="new_variants[{{ $i }}][weight_lb]" class="form-control form-control-sm" value="{{ $variant['weight_lb'] }}" style="width:80px"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3">Product Images</h6>
                    <p class="small text-muted-slate">Uncheck to remove, or upload more. Up to 5 images total.</p>
                    <div class="row g-2 mb-3" id="vendorImagesRow">
                        @forelse ($product->images ?? [] as $image)
                            <div class="col-6">
                                <div class="cart-item__image position-relative" style="width:100%;height:90px">
                                    <img src="{{ $image }}">
                                    <input type="checkbox" name="keep_images[]" value="{{ $image }}" checked class="position-absolute top-0 start-0 m-1 js-image-input">
                                </div>
                            </div>
                        @empty
                            <div class="col-12 small text-muted-slate">No images yet.</div>
                        @endforelse
                    </div>
                    <label class="form-label small">Upload More Images</label>
                    <input type="file" name="custom_images[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm js-image-input" id="customImages">
                    <div class="form-text">JPG/PNG/WEBP, 5MB max per image.</div>
                    <div class="small mt-2" id="imageCounter"></div>
                </div>

                <div class="table-admin p-4 mb-3">
                    <h6 class="mb-3">Product Video <span class="text-muted-slate small">(optional)</span></h6>
                    @if ($product->video_path)
                        <video src="{{ asset('storage/'.$product->video_path) }}" controls style="width:100%;border-radius:8px" class="mb-2"></video>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="remove_video" value="1" id="removeVideo">
                            <label class="form-check-label small" for="removeVideo">Remove current video</label>
                        </div>
                        <label class="form-label small">Replace with</label>
                    @endif
                    <input type="file" name="video" accept="video/mp4" class="form-control form-control-sm">
                    <div class="form-text">MP4 only, 20MB max.</div>
                </div>

                <div class="table-admin p-4">
                    <h6 class="mb-3">Publish</h6>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeToggle" @checked($product->is_active)>
                        <label class="form-check-label small" for="activeToggle">Active (visible to customers)</label>
                    </div>
                    <button type="submit" class="btn btn-accent w-100">Save Changes</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            var categorySelect = document.getElementById('shopCategory');
            var subcategorySelect = document.getElementById('shopSubcategory');

            categorySelect.addEventListener('change', function () {
                var selected = categorySelect.options[categorySelect.selectedIndex];
                var subs = selected && selected.dataset.subcategories ? JSON.parse(selected.dataset.subcategories) : [];
                subcategorySelect.innerHTML = subs.length
                    ? subs.map(function (s) { return '<option value="' + s.id + '">' + s.name + '</option>'; }).join('')
                    : '<option value="">No subcategories</option>';
            });
        })();

        (function () {
            var MAX_IMAGES = 5;
            var counter = document.getElementById('imageCounter');
            var submitBtn = document.querySelector('#productForm button[type="submit"]');
            var inputs = document.querySelectorAll('.js-image-input');

            function countSelected() {
                var checked = document.querySelectorAll('#vendorImagesRow input[type="checkbox"]:checked').length;
                var uploaded = document.getElementById('customImages').files.length;
                return checked + uploaded;
            }

            function refresh() {
                var total = countSelected();
                var overLimit = total > MAX_IMAGES;
                counter.textContent = total + ' / ' + MAX_IMAGES + ' images selected' + (overLimit ? ' — please remove some.' : '');
                counter.style.color = overLimit ? '#DC2626' : '';
                submitBtn.disabled = overLimit;
            }

            inputs.forEach(function (input) {
                input.addEventListener('change', refresh);
            });
            refresh();
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
