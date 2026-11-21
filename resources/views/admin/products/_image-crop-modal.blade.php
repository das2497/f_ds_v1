{{--
    One shared modal per page — admin-image-crop.js wires it to every
    `input.js-crop-input` (gallery + variant photo uploads) via a single
    delegated listener, so this only needs to be included once regardless
    of how many file inputs the page has.
--}}
<div class="modal fade" id="imageCropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crop Photo</h5>
            </div>
            <div class="modal-body">
                <p class="small text-muted-slate mb-2">Drag to position, scroll to zoom. The overlay previews where the logo/border template will land — it's merged into the photo when you save.</p>
                <div class="js-crop-stage" style="position:relative;width:100%;max-width:480px;margin:0 auto">
                    <div style="width:100%;height:420px;background:#111;overflow:hidden">
                        <img class="js-crop-image" style="display:block;max-width:100%">
                    </div>
                    <img class="js-crop-template-overlay" style="position:absolute;pointer-events:none;display:none">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary js-crop-skip">Skip This Photo</button>
                <button type="button" class="btn btn-accent js-crop-apply">Use This Crop</button>
            </div>
        </div>
    </div>
</div>
