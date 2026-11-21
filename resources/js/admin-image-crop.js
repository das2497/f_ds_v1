/* Square-crop + logo/border template preview for every admin product and
 * variant photo upload. Wired to any <input type="file" class="js-crop-input">
 * via @include('admin.products._image-crop-modal') — see that partial for
 * the shared modal markup and app/Services/Images/ImageOptimizer.php for
 * where the template actually gets baked into the saved file server-side
 * (this modal only previews it; cropping is the only thing done here).
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

var OUTPUT_SIZE = 1200;

document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('imageCropModal');
    if (!modalEl || !window.bootstrap) {
        return;
    }

    var modal = new window.bootstrap.Modal(modalEl);
    var stage = modalEl.querySelector('.js-crop-stage');
    var imageEl = modalEl.querySelector('.js-crop-image');
    var overlayEl = modalEl.querySelector('.js-crop-template-overlay');
    var applyBtn = modalEl.querySelector('.js-crop-apply');
    var skipBtn = modalEl.querySelector('.js-crop-skip');
    var cropper = null;

    // Capture phase on `document`, not a listener on each input: it runs
    // before any listener an input already has of its own (those fire in
    // the event's "target" phase, which always comes after `document`'s
    // capture phase), so stopImmediatePropagation() below reliably stops
    // the *original*, uncropped file from ever reaching a page's own
    // preview/thumbnail code.
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!(input instanceof HTMLInputElement) || !input.classList.contains('js-crop-input')) {
            return;
        }

        // Our own re-dispatch (after cropping) — let it through untouched
        // so the page's normal preview/counter logic runs on the result.
        if (input.dataset.cropSynthetic === '1') {
            delete input.dataset.cropSynthetic;
            return;
        }

        if (!input.files || !input.files.length) {
            return;
        }

        e.stopImmediatePropagation();
        e.preventDefault();

        var templateUrl = input.dataset.templateUrl || '';
        var originalFiles = Array.prototype.slice.call(input.files);

        cropEach(originalFiles, templateUrl).then(function (processedFiles) {
            var dataTransfer = new DataTransfer();
            processedFiles.forEach(function (file) {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;
            input.dataset.cropSynthetic = '1';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }, true);

    function cropEach(files, templateUrl) {
        var results = [];
        return files
            .reduce(function (chain, file) {
                return chain.then(function () {
                    return cropOne(file, templateUrl).then(function (cropped) {
                        if (cropped) {
                            results.push(cropped);
                        }
                    });
                });
            }, Promise.resolve())
            .then(function () {
                return results;
            });
    }

    function cropOne(file, templateUrl) {
        return new Promise(function (resolve) {
            var objectUrl = URL.createObjectURL(file);
            var outcome = null;

            imageEl.src = objectUrl;
            if (templateUrl) {
                overlayEl.src = templateUrl;
                overlayEl.style.display = '';
            } else {
                overlayEl.style.display = 'none';
            }

            function onApply() {
                var canvas = cropper.getCroppedCanvas({
                    width: OUTPUT_SIZE,
                    height: OUTPUT_SIZE,
                    imageSmoothingQuality: 'high',
                });
                // JPEG, not PNG: this is the source photo the server will
                // composite the (actually transparent) template onto, and a
                // lossless PNG re-encode of a busy photo can run several
                // times larger than the original — enough to blow past the
                // form's 5MB cap on a photo that fit fine before cropping.
                canvas.toBlob(function (blob) {
                    outcome = blob ? new File([blob], renameToJpg(file.name), { type: 'image/jpeg' }) : null;
                    modal.hide();
                }, 'image/jpeg', 0.92);
            }

            function onSkip() {
                outcome = null;
                modal.hide();
            }

            function onHidden() {
                applyBtn.removeEventListener('click', onApply);
                skipBtn.removeEventListener('click', onSkip);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                URL.revokeObjectURL(objectUrl);
                resolve(outcome);
            }

            applyBtn.addEventListener('click', onApply);
            skipBtn.addEventListener('click', onSkip);
            modalEl.addEventListener('hidden.bs.modal', onHidden);

            modalEl.addEventListener(
                'shown.bs.modal',
                function onShown() {
                    modalEl.removeEventListener('shown.bs.modal', onShown);
                    cropper = new Cropper(imageEl, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        cropBoxMovable: false,
                        cropBoxResizable: false,
                        background: false,
                        ready: positionOverlay,
                    });
                },
                { once: true }
            );

            modal.show();
        });
    }

    // The crop box never moves (locked above) — this only needs to run
    // once, right after Cropper lays itself out.
    function positionOverlay() {
        if (!cropper) {
            return;
        }
        var box = cropper.getCropBoxData();
        var containerEl = modalEl.querySelector('.cropper-container');
        var containerRect = containerEl.getBoundingClientRect();
        var stageRect = stage.getBoundingClientRect();

        overlayEl.style.left = containerRect.left - stageRect.left + box.left + 'px';
        overlayEl.style.top = containerRect.top - stageRect.top + box.top + 'px';
        overlayEl.style.width = box.width + 'px';
        overlayEl.style.height = box.height + 'px';
    }

    function renameToJpg(name) {
        return name.replace(/\.[^.]+$/, '') + '.jpg';
    }
});
