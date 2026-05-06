/* ================================================
   INVOLVE - REUSABLE IMAGE CROPPER
   ================================================

    SECTION MAP:
   1. Find Crop Forms
   2. Open Crop Modal and Preview Image
   3. Track Crop Position and Zoom
   4. Write Crop Fields Before Submit

    WORK GUIDE:
   - Edit this file for profile/org image cropping behavior.
   ================================================ */

(function () {
    var forms = document.querySelectorAll('[data-image-crop-form]');
    if (!forms.length) {
        return;
    }

    var cropperState = null;
    var cropperModal = null;
    var cropperImage = null;
    var cropperStage = null;
    var cropperGuide = null;
    var cropperZoom = null;
    var cropperZoomLabel = null;
    var cropperFileName = null;
    var cropperFileSize = null;
    var cropperInstruction = null;
    var cropperCancel = null;
    var cropperClose = null;
    var cropperSave = null;
    var cropperReset = null;
    var styleTag = null;

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function formatFileSize(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return 'Unknown size';
        }

        var units = ['B', 'KB', 'MB', 'GB'];
        var unitIndex = 0;
        var size = bytes;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex += 1;
        }

        return size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1) + ' ' + units[unitIndex];
    }

    function createStyleTag() {
        if (styleTag) {
            return;
        }

        styleTag = document.createElement('style');
        styleTag.setAttribute('data-image-cropper-style', '1');
        styleTag.textContent = '' +
            '.image-cropper-overlay{display:flex;align-items:center;justify-content:center;backdrop-filter:blur(14px);}' +
            '.image-cropper-overlay.hidden{display:none !important;}' +
            '.image-cropper-panel{width:min(100%,60rem);max-height:min(92dvh,60rem);overflow-y:auto;}' +
            '.image-cropper-stage{position:relative;overflow:hidden;touch-action:none;user-select:none;aspect-ratio:1/1;min-height:28rem;}' +
            '.image-cropper-stage::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at center,rgba(255,255,255,.05),transparent 65%);pointer-events:none;}' +
            '.image-cropper-image{position:absolute;left:0;top:0;transform-origin:0 0;will-change:transform;cursor:grab;}' +
            '.image-cropper-image.is-dragging{cursor:grabbing;}' +
            '.image-cropper-guide{position:absolute;inset:50% auto auto 50%;width:min(80%,28rem);height:min(80%,28rem);transform:translate(-50%,-50%);border-radius:9999px;border:2px solid rgba(255,255,255,.8);box-shadow:0 0 0 9999px rgba(2,6,23,.35);pointer-events:none;}' +
            '.image-cropper-grid{position:absolute;inset:0;pointer-events:none;opacity:.35;background-image:linear-gradient(to right, rgba(255,255,255,.12) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,.12) 1px, transparent 1px);background-size:33.333% 100%, 100% 33.333%;clip-path:circle(36% at center);}' +
            '.image-cropper-button{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:2.75rem;padding:.75rem 1rem;border-radius:9999px;font-weight:600;transition:transform .15s ease,background-color .15s ease,color .15s ease;position:relative;z-index:2;pointer-events:auto;}' +
            '.image-cropper-button:hover{transform:translateY(-1px);}' +
            '.image-cropper-button:disabled{opacity:.55;cursor:not-allowed;transform:none;}' +
            '.image-cropper-button-primary{background:#10b981;color:#06281f;}' +
            '.image-cropper-button-secondary{background:rgba(255,255,255,.08);color:#fff;}' +
            '.image-cropper-range{accent-color:#34d399;}' +
            '.image-cropper-filebox{border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);}' +
            '.image-cropper-kbd{display:inline-flex;align-items:center;justify-content:center;min-width:1.75rem;height:1.75rem;padding:0 .45rem;border-radius:.5rem;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);font-size:.75rem;font-weight:700;color:rgba(255,255,255,.92);}' +
            '.image-cropper-preview-fallback img{width:100%;height:100%;object-fit:cover;border-radius:9999px;display:block;}' +
            '.image-cropper-modal-open{overflow:hidden;}';

        document.head.appendChild(styleTag);
    }

    function ensureModal() {
        if (cropperModal) {
            return;
        }

        createStyleTag();

        cropperModal = document.createElement('div');
        cropperModal.id = 'imageCropperModal';
        cropperModal.className = 'fixed inset-0 z-[80] hidden image-cropper-overlay bg-slate-950/85 px-4 py-6';
        cropperModal.setAttribute('role', 'dialog');
        cropperModal.setAttribute('aria-modal', 'true');
        cropperModal.setAttribute('aria-labelledby', 'imageCropperTitle');

        cropperModal.innerHTML = '' +
            '<div class="image-cropper-panel rounded-3xl border border-white/10 bg-slate-950 text-white shadow-[0_35px_120px_rgba(0,0,0,.45)]" data-cropper-panel>' +
                '<div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">' +
                    '<div class="space-y-1">' +
                        '<p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-300">Photo editor</p>' +
                        '<h2 id="imageCropperTitle" class="text-xl font-semibold">Crop your photo</h2>' +
                        '<p id="imageCropperInstruction" class="text-sm text-slate-300">Drag the photo to reposition it inside the circular frame.</p>' +
                    '</div>' +
                    '<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-2xl leading-none text-slate-200 transition-colors hover:bg-white/10" data-cropper-close aria-label="Close crop editor">&times;</button>' +
                '</div>' +
                '<div class="grid gap-5 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_17rem]">' +
                    '<div class="space-y-4">' +
                        '<div class="image-cropper-stage rounded-3xl border border-white/10 bg-black/50 shadow-inner" data-cropper-stage>' +
                            '<img alt="Selected image" class="image-cropper-image max-w-none select-none" data-cropper-image draggable="false">' +
                            '<div class="image-cropper-grid"></div>' +
                            '<div class="image-cropper-guide"></div>' +
                        '</div>' +
                        '<div class="space-y-2 rounded-2xl border border-white/10 bg-white/5 p-4">' +
                            '<div class="flex items-center justify-between gap-3 text-sm text-slate-200">' +
                                '<span class="font-medium">Zoom</span>' +
                                '<span class="font-semibold text-emerald-300" data-cropper-zoom-label>100%</span>' +
                            '</div>' +
                            '<input type="range" min="1" max="3" step="0.01" value="1" class="image-cropper-range w-full" data-cropper-zoom>' +
                        '</div>' +
                        '<div class="flex flex-wrap items-center gap-2 text-xs text-slate-300">' +
                            '<span class="image-cropper-kbd">Drag</span><span>to move the image</span>' +
                            '<span class="image-cropper-kbd">Zoom</span><span>to fill the frame</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="space-y-4">' +
                        '<div class="image-cropper-filebox rounded-2xl p-4">' +
                            '<p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Selected file</p>' +
                            '<p class="mt-2 break-words text-sm font-medium text-white" data-cropper-file-name>No file selected</p>' +
                            '<p class="mt-1 text-xs text-slate-400" data-cropper-file-size>Choose an image to continue.</p>' +
                        '</div>' +
                        '<div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">' +
                            '<p class="font-semibold text-white">Tip</p>' +
                            '<p class="mt-2 text-slate-300">Center the face or logo, then crop it into a ready-to-upload image. The saved file will already match the frame.</p>' +
                        '</div>' +
                        '<div class="flex flex-col gap-3 sm:flex-row lg:flex-col">' +
                            '<button type="button" class="image-cropper-button image-cropper-button-primary w-full" data-cropper-save>Save profile pic</button>' +
                            '<button type="button" class="image-cropper-button image-cropper-button-secondary w-full" data-cropper-reset>Reset</button>' +
                            '<button type="button" class="image-cropper-button image-cropper-button-secondary w-full lg:hidden" data-cropper-cancel>Cancel</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="sticky bottom-0 z-10 border-t border-white/10 bg-slate-950/95 px-5 py-4 text-xs text-slate-400">The editor preserves a square crop for profile photos and organization logos.</div>' +
            '</div>';

        document.body.appendChild(cropperModal);

        cropperImage = cropperModal.querySelector('[data-cropper-image]');
        cropperStage = cropperModal.querySelector('[data-cropper-stage]');
        cropperGuide = cropperModal.querySelector('.image-cropper-guide');
        cropperZoom = cropperModal.querySelector('[data-cropper-zoom]');
        cropperZoomLabel = cropperModal.querySelector('[data-cropper-zoom-label]');
        cropperFileName = cropperModal.querySelector('[data-cropper-file-name]');
        cropperFileSize = cropperModal.querySelector('[data-cropper-file-size]');
        cropperInstruction = cropperModal.querySelector('#imageCropperInstruction');
        cropperCancel = cropperModal.querySelector('[data-cropper-cancel]');
        cropperClose = cropperModal.querySelector('[data-cropper-close]');
        cropperSave = cropperModal.querySelector('[data-cropper-save]');
        cropperReset = cropperModal.querySelector('[data-cropper-reset]');

        cropperModal.addEventListener('click', function (event) {
            if (event.target === cropperModal) {
                closeCropper(true);
            }
        });

        [cropperClose, cropperCancel].forEach(function (button) {
            if (!button) {
                return;
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeCropper(true);
            });
        });

        cropperReset.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!cropperState) {
                return;
            }

            cropperState.translateX = 0;
            cropperState.translateY = 0;
            cropperState.zoomFactor = 1;
            cropperZoom.value = '1';
            updateCropper();
        });

        cropperZoom.addEventListener('input', function () {
            if (!cropperState) {
                return;
            }

            cropperState.zoomFactor = Number(cropperZoom.value) || 1;
            clampTranslation();
            updateCropper();
        });

        cropperSave.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!cropperState) {
                return;
            }

            renderCroppedFile().then(function (file) {
                if (!file) {
                    return;
                }

                var form = cropperState.form;
                var shouldAutoSubmit = form && form.hasAttribute('data-crop-auto-submit');

                assignFileToInput(cropperState.input, file);
                refreshPreview(form, file);
                syncHiddenCropFields(form);
                closeCropper(false);

                if (shouldAutoSubmit && typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                }
            }).catch(function () {
                closeCropper(true);
            });
        });

        cropperStage.addEventListener('pointerdown', function (event) {
            if (!cropperState) {
                return;
            }

            event.preventDefault();
            cropperState.dragging = true;
            cropperState.pointerId = event.pointerId;
            cropperState.startX = event.clientX;
            cropperState.startY = event.clientY;
            cropperState.originTranslateX = cropperState.translateX;
            cropperState.originTranslateY = cropperState.translateY;
            cropperImage.classList.add('is-dragging');
            cropperStage.setPointerCapture(event.pointerId);
        });

        cropperStage.addEventListener('pointermove', function (event) {
            if (!cropperState || !cropperState.dragging) {
                return;
            }

            var deltaX = event.clientX - cropperState.startX;
            var deltaY = event.clientY - cropperState.startY;

            cropperState.translateX = cropperState.originTranslateX + deltaX;
            cropperState.translateY = cropperState.originTranslateY + deltaY;
            clampTranslation();
            updateCropper();
        });

        var finishDrag = function () {
            if (!cropperState || !cropperState.dragging) {
                return;
            }

            cropperState.dragging = false;
            cropperImage.classList.remove('is-dragging');

            if (cropperState.pointerId !== null && cropperStage.hasPointerCapture(cropperState.pointerId)) {
                cropperStage.releasePointerCapture(cropperState.pointerId);
            }

            cropperState.pointerId = null;
        };

        cropperStage.addEventListener('pointerup', finishDrag);
        cropperStage.addEventListener('pointercancel', finishDrag);
        cropperStage.addEventListener('pointerleave', finishDrag);

        window.addEventListener('resize', function () {
            if (!cropperState) {
                return;
            }

            clampTranslation();
            updateCropper();
        });

        document.addEventListener('keydown', function (event) {
            if (!cropperState) {
                return;
            }

            if (event.key === 'Escape') {
                closeCropper(true);
                return;
            }

            var nudge = event.shiftKey ? 18 : 8;
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                cropperState.translateX -= nudge;
                clampTranslation();
                updateCropper();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                cropperState.translateX += nudge;
                clampTranslation();
                updateCropper();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                cropperState.translateY -= nudge;
                clampTranslation();
                updateCropper();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                cropperState.translateY += nudge;
                clampTranslation();
                updateCropper();
            }
        });
    }

    function syncHiddenCropFields(form) {
        var cropX = form.querySelector('[name$="_crop_x"]');
        var cropY = form.querySelector('[name$="_crop_y"]');
        var zoom = form.querySelector('[name$="_zoom"]');

        if (cropX) {
            cropX.value = '50';
        }

        if (cropY) {
            cropY.value = '50';
        }

        if (zoom) {
            zoom.value = '1';
        }
    }

    function assignFileToInput(input, file) {
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
    }

    function getPreviewContainer(form) {
        return form.querySelector('[data-crop-preview]') || form.querySelector('[data-image-preview]') || form.querySelector('.shrink-0');
    }

    function refreshPreview(form, file) {
        var preview = getPreviewContainer(form);
        if (!preview) {
            return;
        }

        var url = URL.createObjectURL(file);
        var entity = form.querySelector('input[name="logo"]') ? 'organization' : 'user';
        var sizeClass = entity === 'organization' ? 'h-20 w-20' : 'h-20 w-20';
        var label = entity === 'organization' ? 'Selected logo' : 'Selected photo';

        preview.innerHTML = '<div class="image-cropper-preview-fallback overflow-hidden rounded-full border border-white/10 bg-slate-100 ' + sizeClass + '"><img src="' + url + '" alt="' + label + '"></div>';

        // Keep the profile "View profile" modal in sync with the cropped output.
        var largePreviewFrame = document.querySelector('[data-profile-picture-preview-large]');
        if (!largePreviewFrame || entity !== 'user') {
            return;
        }

        largePreviewFrame.innerHTML = '';
        var largeImage = document.createElement('img');
        largeImage.src = url;
        largeImage.alt = label;
        largeImage.className = 'profile-picture-preview-image';
        largePreviewFrame.appendChild(largeImage);
    }

    function closeCropper(clearInput) {
        if (cropperState && clearInput) {
            cropperState.input.value = '';
        }

        if (cropperModal) {
            cropperModal.classList.add('hidden');
            cropperModal.style.display = 'none';
        }

        document.body.classList.remove('image-cropper-modal-open');
        if (cropperState && cropperState.objectUrl) {
            URL.revokeObjectURL(cropperState.objectUrl);
        }

        cropperState = null;
    }

    function updateCropper() {
        if (!cropperState) {
            return;
        }

        var stageBox = cropperStage.getBoundingClientRect();
        var renderWidth = stageBox.width || 1;
        var renderHeight = stageBox.height || 1;
        var scale = cropperState.baseScale * cropperState.zoomFactor;
        var width = cropperState.image.naturalWidth * scale;
        var height = cropperState.image.naturalHeight * scale;
        var left = (renderWidth - width) / 2 + cropperState.translateX;
        var top = (renderHeight - height) / 2 + cropperState.translateY;

        cropperImage.style.width = width + 'px';
        cropperImage.style.height = height + 'px';
        cropperImage.style.transform = 'translate(' + left + 'px, ' + top + 'px)';
        cropperZoomLabel.textContent = Math.round(cropperState.zoomFactor * 100) + '%';
    }

    function getCropFrameMetrics() {
        var stageBox = cropperStage.getBoundingClientRect();
        var frameLeft = 0;
        var frameTop = 0;
        var frameWidth = stageBox.width;
        var frameHeight = stageBox.height;

        if (cropperGuide) {
            var guideBox = cropperGuide.getBoundingClientRect();
            frameLeft = guideBox.left - stageBox.left;
            frameTop = guideBox.top - stageBox.top;
            frameWidth = guideBox.width;
            frameHeight = guideBox.height;
        }

        return {
            stageBox: stageBox,
            frameLeft: frameLeft,
            frameTop: frameTop,
            frameWidth: frameWidth,
            frameHeight: frameHeight
        };
    }

    function clampTranslation() {
        if (!cropperState) {
            return;
        }

        var metrics = getCropFrameMetrics();
        var renderWidth = metrics.stageBox.width || 1;
        var renderHeight = metrics.stageBox.height || 1;
        var scale = cropperState.baseScale * cropperState.zoomFactor;
        var width = cropperState.image.naturalWidth * scale;
        var height = cropperState.image.naturalHeight * scale;
        var centerX = renderWidth / 2;
        var centerY = renderHeight / 2;
        var frameCenterX = metrics.frameLeft + (metrics.frameWidth / 2);
        var frameCenterY = metrics.frameTop + (metrics.frameHeight / 2);
        var deltaCenterX = frameCenterX - centerX;
        var deltaCenterY = frameCenterY - centerY;
        var limitX = Math.max(0, (width - metrics.frameWidth) / 2);
        var limitY = Math.max(0, (height - metrics.frameHeight) / 2);

        cropperState.translateX = clamp(cropperState.translateX, deltaCenterX - limitX, deltaCenterX + limitX);
        cropperState.translateY = clamp(cropperState.translateY, deltaCenterY - limitY, deltaCenterY + limitY);
    }

    function renderCroppedFile() {
        return new Promise(function (resolve, reject) {
            if (!cropperState) {
                resolve(null);
                return;
            }

            var metrics = getCropFrameMetrics();
            var stageBox = metrics.stageBox;
            var outputSize = 1200;
            var scale = cropperState.baseScale * cropperState.zoomFactor;
            var width = cropperState.image.naturalWidth * scale;
            var height = cropperState.image.naturalHeight * scale;
            var left = (stageBox.width - width) / 2 + cropperState.translateX;
            var top = (stageBox.height - height) / 2 + cropperState.translateY;
            var sourceX = (metrics.frameLeft - left) / scale;
            var sourceY = (metrics.frameTop - top) / scale;
            var sourceWidth = metrics.frameWidth / scale;
            var sourceHeight = metrics.frameHeight / scale;
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d');

            if (!context) {
                reject(new Error('Canvas is unavailable'));
                return;
            }

            canvas.width = outputSize;
            canvas.height = outputSize;
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(
                cropperState.image,
                sourceX,
                sourceY,
                sourceWidth,
                sourceHeight,
                0,
                0,
                canvas.width,
                canvas.height
            );

            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('Failed to crop image'));
                    return;
                }

                var mimeType = blob.type || 'image/png';
                var extension = 'png';
                if (mimeType === 'image/jpeg') {
                    extension = 'jpg';
                } else if (mimeType === 'image/webp') {
                    extension = 'webp';
                }

                var baseName = (cropperState.file.name || 'cropped-image').replace(/\.[^.]+$/, '');
                var croppedFile = new File([blob], baseName + '-cropped.' + extension, { type: mimeType, lastModified: Date.now() });
                resolve(croppedFile);
            }, cropperState.file.type === 'image/jpeg' ? 'image/jpeg' : cropperState.file.type === 'image/webp' ? 'image/webp' : 'image/png', 0.95);
        });
    }

    function openCropper(form, input, file) {
        ensureModal();

        if (!file || !/^image\//i.test(file.type || '')) {
            input.value = '';
            return;
        }

        var objectUrl = URL.createObjectURL(file);
        var image = new Image();

        cropperModal.classList.remove('hidden');
        cropperModal.style.display = 'flex';
        document.body.classList.add('image-cropper-modal-open');
        cropperFileName.textContent = file.name || 'Selected image';
        cropperFileSize.textContent = formatFileSize(file.size);
        cropperInstruction.textContent = 'Drag the photo to reposition it inside the circular frame.';
        cropperZoom.value = '1';
        cropperZoomLabel.textContent = '100%';
        cropperSave.disabled = true;
        cropperImage.removeAttribute('src');

        cropperState = {
            form: form,
            input: input,
            file: file,
            image: image,
            objectUrl: objectUrl,
            translateX: 0,
            translateY: 0,
            originTranslateX: 0,
            originTranslateY: 0,
            zoomFactor: 1,
            baseScale: 1,
            dragging: false,
            pointerId: null,
            startX: 0,
            startY: 0
        };

        image.onload = function () {
            var metrics = getCropFrameMetrics();
            var fitScale = Math.max(metrics.frameWidth / image.naturalWidth, metrics.frameHeight / image.naturalHeight);
            cropperState.baseScale = fitScale || 1;
            cropperState.translateX = 0;
            cropperState.translateY = 0;
            cropperState.zoomFactor = 1;
            cropperZoom.min = '1';
            cropperZoom.max = '3';
            cropperZoom.step = '0.01';
            cropperZoom.value = '1';
            cropperImage.src = objectUrl;
            cropperSave.disabled = false;
            updateCropper();
        };

        image.onerror = function () {
            input.value = '';
            closeCropper(true);
        };

        image.src = objectUrl;
    }

    forms.forEach(function (form) {
        var input = form.querySelector('[data-image-input]');
        if (!input) {
            return;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }

            openCropper(form, input, file);
        });
    });
})();
