/* Printful product design canvas (Admin) — place text/images on a print
 * placement and save the result as the artwork Printful prints when a
 * customer orders that variant. See
 * app/Http/Controllers/Admin/ProductDesignController.php for the save
 * endpoint and app/Services/Vendor/PrintfulService.php for how the saved
 * `print_file_url` later reaches Printful's order API.
 *
 * Each variant row gets its own modal with its own canvas instance, and a
 * product can have dozens of variants — canvases are only ever built the
 * first time their modal is opened (Bootstrap's `shown.bs.modal`), not
 * upfront for every row on page load.
 */
import * as fabric from 'fabric';

var DISPLAY_WIDTH = 420;

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-design-canvas-modal').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            if (modal.dataset.designInitialized) {
                return;
            }
            modal.dataset.designInitialized = '1';
            initDesignApp(modal.querySelector('.js-design-canvas-app'));
        });
    });
});

function initDesignApp(app) {
    var saveUrl = app.dataset.saveUrl;
    var uploadUrl = app.dataset.uploadUrl;
    var extraFields = {};
    try {
        extraFields = JSON.parse(app.dataset.extraFields || '{}');
    } catch (e) {
        extraFields = {};
    }
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var configEl = app.querySelector('.js-design-config');
    var config = configEl ? JSON.parse(configEl.textContent) : { placements: {}, existingDesign: {} };
    var entries = {};

    app.querySelectorAll('.js-design-panel').forEach(function (panel) {
        var placement = panel.dataset.placement;
        var spec = config.placements[placement] || { width: 1800, height: 2400 };
        var scale = DISPLAY_WIDTH / spec.width;
        var displayHeight = Math.round(spec.height * scale);

        var canvas = new fabric.Canvas(panel.querySelector('.js-design-canvas'), {
            width: DISPLAY_WIDTH,
            height: displayHeight,
        });
        canvas.setZoom(scale);

        var existing = config.existingDesign[placement];
        if (existing && existing.canvas_json) {
            canvas.loadFromJSON(existing.canvas_json, function () {
                canvas.renderAll();
            });
        }

        entries[placement] = { canvas: canvas, spec: spec, scale: scale };
    });

    wireTabs(app);

    app.querySelectorAll('.js-design-panel').forEach(function (panel) {
        wirePanel(panel, entries[panel.dataset.placement], saveUrl, uploadUrl, extraFields, csrfToken);
    });
}

function wireTabs(app) {
    var tabs = app.querySelectorAll('.js-design-tab');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var placement = tab.dataset.placement;
            tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
            app.querySelectorAll('.js-design-panel').forEach(function (panel) {
                panel.style.display = panel.dataset.placement === placement ? '' : 'none';
            });
        });
    });
}

function wirePanel(panel, entry, saveUrl, uploadUrl, extraFields, csrfToken) {
    var canvas = entry.canvas;

    panel.querySelector('.js-design-add-text').addEventListener('click', function () {
        var fontFamily = panel.querySelector('.js-design-font').value;
        var fontSize = parseInt(panel.querySelector('.js-design-font-size').value, 10) || 48;
        var color = panel.querySelector('.js-design-color').value;

        var text = new fabric.IText('Your text', {
            left: Math.round(entry.spec.width * 0.25),
            top: Math.round(entry.spec.height * 0.25),
            fontFamily: fontFamily,
            fontSize: fontSize,
            fill: color,
        });

        canvas.add(text);
        canvas.setActiveObject(text);
        canvas.renderAll();
    });

    panel.querySelector('.js-design-add-image').addEventListener('change', function (event) {
        var input = event.target;
        var file = input.files[0];
        if (!file) {
            return;
        }

        // Uploaded first and added to the canvas by URL — never as a data
        // URI. Fabric serializes an image layer's `src` verbatim into
        // canvas_json on every save; embedding the raw file there bloats
        // the saved design by the image's full size for no reason (it's
        // already captured separately in the flattened print file export).
        var label = input.closest('label');
        input.disabled = true;
        if (label) {
            label.classList.add('disabled');
        }

        var formData = new FormData();
        formData.append('image', file);

        fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: formData,
        })
            .then(function (response) {
                if (!response.ok) {
                    // Surface the server's actual reason (e.g. Laravel's
                    // validation message for an oversized or wrong-type
                    // file) instead of a generic failure — "try again"
                    // alone told an admin nothing when the real cause was
                    // "max 10MB" or an unsupported format.
                    return response
                        .json()
                        .catch(function () { return {}; })
                        .then(function (data) {
                            var detail = (data && data.errors && data.errors.image && data.errors.image[0])
                                || (data && data.message)
                                || ('HTTP ' + response.status);
                            throw new Error(detail);
                        });
                }
                return response.json();
            })
            .then(function (data) {
                // No crossOrigin option — these assets are always served by
                // this same app (never a separate CDN/S3 origin), and
                // setting crossOrigin without the server actually sending
                // CORS headers makes the browser refuse to load the image
                // at all. It also breaks the moment the page's own host
                // doesn't byte-for-byte match APP_URL's host (e.g. the
                // admin panel opened via 127.0.0.1 while APP_URL is
                // "localhost" — same machine, different origin as far as
                // the browser is concerned).
                return fabric.FabricImage.fromURL(data.url);
            })
            .then(function (image) {
                var maxWidth = entry.spec.width * 0.8;
                if (image.width > maxWidth) {
                    image.scale(maxWidth / image.width);
                }

                image.set({
                    left: Math.round(entry.spec.width * 0.25),
                    top: Math.round(entry.spec.height * 0.25),
                });

                canvas.add(image);
                canvas.setActiveObject(image);
                canvas.renderAll();
            })
            .catch(function (error) {
                alert('Could not upload that image: ' + (error && error.message ? error.message : 'try again.'));
            })
            .finally(function () {
                input.disabled = false;
                if (label) {
                    label.classList.remove('disabled');
                }
                input.value = '';
            });
    });

    panel.querySelector('.js-design-delete').addEventListener('click', function () {
        canvas.getActiveObjects().forEach(function (object) { canvas.remove(object); });
        canvas.discardActiveObject();
        canvas.renderAll();
    });

    panel.querySelector('.js-design-clear').addEventListener('click', function () {
        if (canvas.getObjects().length && !confirm("Clear this placement's design?")) {
            return;
        }
        canvas.clear();
    });

    panel.querySelector('.js-design-save').addEventListener('click', function () {
        saveDesign(panel, entry, saveUrl, extraFields, csrfToken);
    });
}

function saveDesign(panel, entry, saveUrl, extraFields, csrfToken) {
    var canvas = entry.canvas;
    var placement = panel.dataset.placement;
    var statusEl = panel.querySelector('.js-design-status');
    var thumbEl = panel.querySelector('.js-design-saved-thumb');
    var saveBtn = panel.querySelector('.js-design-save');

    statusEl.textContent = 'Saving…';
    statusEl.className = 'small text-muted-slate js-design-status';
    saveBtn.disabled = true;

    var dataUrl;
    try {
        // Objects are positioned in full print-resolution coordinates
        // (zoom only shrinks how they're displayed on screen), so
        // exporting with multiplier = 1 / zoom renders back out at the
        // real print-area size Printful expects, not the small on-screen
        // canvas size. This throws (synchronously, not as a rejected
        // promise) if any image ever drawn onto this canvas came from a
        // different-looking origin without CORS approval — it "taints"
        // the canvas for every future export, even after that image is
        // removed. Caught here so the button recovers with a real error
        // instead of staying stuck on "Saving…" forever.
        dataUrl = canvas.toDataURL({ format: 'png', multiplier: 1 / canvas.getZoom() });
    } catch (error) {
        statusEl.textContent = 'Could not export the design — reload and try again.';
        statusEl.className = 'small text-danger js-design-status';
        saveBtn.disabled = false;
        return;
    }

    var body = Object.assign({}, extraFields, {
        placement: placement,
        canvas_json: JSON.stringify(canvas.toJSON()),
        print_file_data: dataUrl,
        width: entry.spec.width,
        height: entry.spec.height,
    });

    fetch(saveUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(body),
    })
        .then(function (response) {
            if (!response.ok) {
                return response
                    .json()
                    .catch(function () { return {}; })
                    .then(function (data) {
                        throw new Error((data && data.message) || ('HTTP ' + response.status));
                    });
            }
            return response.json();
        })
        .then(function (data) {
            statusEl.textContent = 'Saved';
            statusEl.className = 'small text-success js-design-status';
            if (thumbEl) {
                thumbEl.src = data.print_file_url;
                thumbEl.style.display = '';
            }
        })
        .catch(function (error) {
            statusEl.textContent = 'Could not save: ' + (error && error.message ? error.message : 'try again.');
            statusEl.className = 'small text-danger js-design-status';
        })
        .finally(function () {
            saveBtn.disabled = false;
        });
}
