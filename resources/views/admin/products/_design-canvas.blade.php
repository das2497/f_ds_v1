{{--
    Printful print-on-demand design canvas, opened from a "Design" button on
    one variant row. Included once per variant from both
    admin/products/create.blade.php (pre-publish draft, saved against the
    VendorProductCandidate keyed by the row's vendor SKU — no real
    ProductVariant row exists yet) and admin/products/edit.blade.php (saved
    directly against that ProductVariant). The canvas itself — tabs,
    toolbar, save — is identical either way; only where it saves differs.
    Callers pass:
      $modalId         string   unique DOM id for this row's modal
      $variantLabel    string   shown in the modal title
      $saveUrl         string   endpoint this placement's "Save Design" posts to
      $placements      array    ['front' => ['width'=>..,'height'=>..,'dpi'=>..], ...]
      $existingDesign  array    previously saved design_data, keyed by placement
      $referenceImage  ?string  faint backdrop shown behind the canvas for visual reference only
      $extraFields     array    extra key => value fields posted alongside the save (e.g. vendor_sku)
--}}
<div class="modal fade js-design-canvas-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-brush me-2"></i>Design — {{ $variantLabel }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body js-design-canvas-app"
                 data-save-url="{{ $saveUrl }}"
                 data-upload-url="{{ route('admin.product-designs.upload-image') }}"
                 data-extra-fields="{{ json_encode($extraFields ?? []) }}">
                <p class="small text-muted-slate">Place text and artwork directly on the print area. This exact design is what Printful prints when a customer orders this variant.</p>

                <ul class="nav nav-pills mb-3">
                    @foreach ($placements as $placement => $spec)
                        <li class="nav-item">
                            <button type="button" class="nav-link js-design-tab {{ $loop->first ? 'active' : '' }}" data-placement="{{ $placement }}">{{ ucfirst($placement) }}</button>
                        </li>
                    @endforeach
                </ul>

                @foreach ($placements as $placement => $spec)
                    <div class="js-design-panel" data-placement="{{ $placement }}" @if (! $loop->first) style="display:none" @endif>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary js-design-add-text"><i class="bi bi-type"></i> Add Text</button>
                            <label class="btn btn-sm btn-outline-secondary mb-0">
                                <i class="bi bi-image"></i> Add Image
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="d-none js-design-add-image">
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-design-delete"><i class="bi bi-trash"></i> Delete Selected</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-design-clear">Clear</button>
                            <span class="vr mx-1"></span>
                            <input type="color" class="form-control form-control-sm js-design-color" style="width:42px;padding:2px" value="#111827" title="Text color">
                            <select class="form-select form-select-sm js-design-font" style="width:140px" title="Font">
                                <option value="Arial">Arial</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Impact">Impact</option>
                                <option value="Verdana">Verdana</option>
                            </select>
                            <input type="number" class="form-control form-control-sm js-design-font-size" style="width:70px" value="48" min="8" title="Font size">
                        </div>

                        <div class="design-canvas-wrap" style="position:relative;width:420px;max-width:100%;background:#F8FAFC;border:1px solid var(--color-border);border-radius:8px;overflow:hidden">
                            {{-- Shown at near-full strength so the product's real color
                                 reads correctly (a partial opacity blends toward the
                                 canvas's white background and washes out dark colors) —
                                 it's still just a backdrop behind the canvas, not
                                 something the exported print file includes; only the
                                 canvas layers drawn on top of it get exported. --}}
                            @if ($referenceImage)
                                <img src="{{ $referenceImage }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;opacity:.9;pointer-events:none">
                            @endif
                            <canvas class="js-design-canvas"></canvas>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-accent js-design-save">Save Design</button>
                            <span class="small js-design-status"></span>
                            <img class="js-design-saved-thumb ms-auto" style="width:40px;height:40px;object-fit:contain;border:1px solid var(--color-border);border-radius:6px;display:none" alt="Saved design preview">
                        </div>

                        <div class="form-text">Print area: {{ number_format($spec['width']) }}×{{ number_format($spec['height']) }}px{{ isset($spec['dpi']) ? ' @ '.$spec['dpi'].' DPI' : '' }}.</div>
                    </div>
                @endforeach

                <script type="application/json" class="js-design-config">{!! json_encode(['placements' => $placements, 'existingDesign' => $existingDesign]) !!}</script>
            </div>
        </div>
    </div>
</div>

@once
    @vite(['resources/js/admin-design-canvas.js'])
@endonce
