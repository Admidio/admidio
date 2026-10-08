/**
 * Attach Quill to an existing Admidio editor field. The textarea remains the form value and
 * a working fallback when JavaScript or the editor cannot initialize.
 */
function admidioInitQuill(id, options) {
    const textarea = document.getElementById(id);
    const container = document.getElementById(id + '_quill');
    if (!textarea || !container || typeof Quill === 'undefined') {
        return;
    }

    let quill;
    try {
        // Quill's default video exporter turns embeds into links. Keep the iframe
        // so an announcement can be saved and edited again with its video intact.
        const Video = Quill.import('formats/video');
        class AnnouncementVideo extends Video {
            html() {
                return this.domNode.outerHTML;
            }
        }
        Quill.register('formats/video', AnnouncementVideo, true);
        Quill.register(Quill.import('attributors/style/size'), true);
        Quill.register(Quill.import('attributors/style/align'), true);

        container.style.display = '';
        quill = new Quill(container, {
            theme: 'snow',
            modules: {
                table: true,
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ size: ['10px', false, '18px', '32px'] }],
                    [{ color: [false, '#000000', '#e60000', '#ff9900', '#ffff00', '#008a00',
                        '#0066cc', '#9933ff', '#ffffff', '#888888', '#5c0000', '#b26b00',
                        '#006100', '#002966', '#3d1466'] }],
                    [{ align: [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });
        quill.root.style.minHeight = '12rem';
        quill.root.setAttribute('aria-labelledby', id + '_label');
        quill.root.setAttribute('aria-multiline', 'true');

        if (textarea.value !== '') {
            quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
        }
    } catch (error) {
        if (quill) {
            quill.getModule('toolbar').container.remove();
        }
        container.style.display = 'none';
        console.error(error);
        return;
    }

    textarea.style.display = 'none';
    const form = textarea.form;
    let changed = false;
    const syncValue = function () {
        textarea.value = quill.getText().trim() === '' && !quill.root.querySelector('img, table, iframe')
            ? ''
            : quill.getSemanticHTML();
    };

    quill.on('text-change', function (delta, oldDelta, source) {
        if (source === 'user') {
            changed = true;
            syncValue();
        }
    });
    form.addEventListener('submit', function () {
        if (changed) {
            syncValue();
        }
    });

    const toolbar = quill.getModule('toolbar');
    const table = quill.getModule('table');
    let lastRange = quill.getSelection() || { index: quill.getLength() - 1, length: 0 };
    quill.on('selection-change', function (range) {
        if (range) {
            lastRange = range;
        }
    });
    const tableActions = [
        'insertTable', 'insertRowAbove', 'insertRowBelow', 'insertColumnLeft',
        'insertColumnRight', 'deleteRow', 'deleteColumn', 'deleteTable'
    ];
    const tableSelect = document.createElement('select');
    tableSelect.className = 'admidio-quill-table-action';
    tableSelect.setAttribute('aria-label', options.labels.table);
    tableSelect.setAttribute('title', options.labels.table);
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = options.labels.table;
    tableSelect.appendChild(placeholder);
    tableActions.forEach(function (action) {
        const option = document.createElement('option');
        option.value = action;
        option.textContent = options.labels[action];
        tableSelect.appendChild(option);
    });
    const tableGroup = document.createElement('span');
    tableGroup.className = 'ql-formats';
    tableGroup.appendChild(tableSelect);
    toolbar.container.insertBefore(tableGroup, toolbar.container.lastElementChild);
    tableSelect.addEventListener('change', function () {
        const action = tableSelect.value;
        tableSelect.value = '';
        if (!action || !lastRange) {
            return;
        }
        quill.setSelection(lastRange.index, lastRange.length, 'silent');
        if (action === 'insertTable') {
            table.insertTable(2, 2);
        } else if (table.getTable()[0]) {
            table[action]();
        }
        changed = true;
        syncValue();
        quill.focus();
    });

    // Quill stores image width and height as formats, so changing them through its API
    // survives HTML serialization and a subsequent edit.
    const resizeHandle = document.createElement('button');
    resizeHandle.type = 'button';
    resizeHandle.className = 'admidio-quill-resize-handle';
    resizeHandle.setAttribute('aria-label', options.labels.resizeImage);
    resizeHandle.setAttribute('title', options.labels.resizeImage);
    resizeHandle.style.display = 'none';
    container.appendChild(resizeHandle);
    let selectedImage = null;
    const positionResizeHandle = function () {
        if (!selectedImage || !selectedImage.isConnected) {
            resizeHandle.style.display = 'none';
            selectedImage = null;
            return;
        }
        const imageRect = selectedImage.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        resizeHandle.style.left = (imageRect.right - containerRect.left - 9) + 'px';
        resizeHandle.style.top = (imageRect.bottom - containerRect.top - 9) + 'px';
        resizeHandle.style.display = '';
    };
    const resizeImage = function (width, ratio) {
        if (!selectedImage) {
            return;
        }
        const blot = Quill.find(selectedImage);
        if (!blot) {
            return;
        }
        const limitedWidth = Math.max(40, Math.min(Math.round(width), quill.root.clientWidth));
        quill.formatText(quill.getIndex(blot), 1, {
            width: String(limitedWidth),
            height: String(Math.round(limitedWidth / ratio))
        }, 'user');
        positionResizeHandle();
    };
    quill.root.addEventListener('click', function (event) {
        selectedImage = event.target.tagName === 'IMG' ? event.target : null;
        positionResizeHandle();
    });
    quill.root.addEventListener('scroll', positionResizeHandle);
    window.addEventListener('resize', positionResizeHandle);
    resizeHandle.addEventListener('pointerdown', function (event) {
        if (!selectedImage) {
            return;
        }
        event.preventDefault();
        const startX = event.clientX;
        const startWidth = selectedImage.getBoundingClientRect().width;
        const ratio = startWidth / selectedImage.getBoundingClientRect().height;
        resizeHandle.setPointerCapture(event.pointerId);
        const move = function (moveEvent) {
            resizeImage(startWidth + moveEvent.clientX - startX, ratio);
        };
        resizeHandle.addEventListener('pointermove', move);
        resizeHandle.addEventListener('pointerup', function () {
            resizeHandle.removeEventListener('pointermove', move);
        }, { once: true });
    });
    resizeHandle.addEventListener('keydown', function (event) {
        if (!selectedImage || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
            return;
        }
        event.preventDefault();
        const rect = selectedImage.getBoundingClientRect();
        resizeImage(rect.width + (event.key === 'ArrowRight' ? 10 : -10), rect.width / rect.height);
    });
    const buttons = {
        bold: 'button.ql-bold',
        italic: 'button.ql-italic',
        underline: 'button.ql-underline',
        ordered: 'button.ql-list[value="ordered"]',
        bullet: 'button.ql-list[value="bullet"]',
        link: 'button.ql-link',
        image: 'button.ql-image',
        video: 'button.ql-video',
        clean: 'button.ql-clean'
    };
    Object.entries(buttons).forEach(function ([name, selector]) {
        const button = toolbar.container.querySelector(selector);
        if (button) {
            button.setAttribute('aria-label', options.labels[name]);
            button.setAttribute('title', options.labels[name]);
        }
    });
    const colorPicker = toolbar.container.querySelector('.ql-color .ql-picker-label');
    if (colorPicker) {
        colorPicker.setAttribute('aria-label', options.labels.fontColor);
        colorPicker.setAttribute('title', options.labels.fontColor);
    }
    toolbar.container.querySelectorAll('.ql-color .ql-picker-item').forEach(function (item) {
        item.setAttribute('aria-label', item.getAttribute('data-value') || options.labels.defaultColor);
    });
    const alignPicker = toolbar.container.querySelector('.ql-picker.ql-align');
    if (alignPicker) {
        const alignLabel = alignPicker.querySelector('.ql-picker-label');
        alignLabel.setAttribute('aria-label', options.labels.alignment);
        alignLabel.setAttribute('title', options.labels.alignment);
        const alignLabels = {
            center: options.labels.alignCenter,
            right: options.labels.alignRight,
            justify: options.labels.alignJustify
        };
        alignPicker.querySelectorAll('.ql-picker-item').forEach(function (item) {
            item.setAttribute('aria-label', alignLabels[item.getAttribute('data-value')]
                || options.labels.alignLeft);
        });
    }
    const sizePicker = toolbar.container.querySelector('.ql-picker.ql-size');
    if (sizePicker) {
        const sizeLabels = {
            '10px': options.labels.sizeSmall,
            '18px': options.labels.sizeLarge,
            '32px': options.labels.sizeHuge
        };
        sizePicker.querySelectorAll('.ql-picker-item').forEach(function (item) {
            const label = sizeLabels[item.getAttribute('data-value')] || options.labels.sizeNormal;
            item.setAttribute('data-label', label);
            item.setAttribute('aria-label', label);
        });
        const pickerLabel = sizePicker.querySelector('.ql-picker-label');
        pickerLabel.setAttribute('aria-label', options.labels.fontSize);
        pickerLabel.setAttribute('title', options.labels.fontSize);
        const selectedSize = sizePicker.querySelector('.ql-picker-item.ql-selected');
        pickerLabel.setAttribute('data-label', selectedSize
            ? selectedSize.getAttribute('data-label') : options.labels.sizeNormal);
    }

    toolbar.addHandler('link', function () {
        const range = quill.getSelection(true);
        if (!range) {
            return;
        }
        const currentUrl = quill.getFormat(range).link || 'https://';
        const url = window.prompt(options.labels.link, currentUrl);
        if (url !== null) {
            quill.setSelection(range.index, range.length);
            quill.format('link', url || false, 'user');
        }
    });

    toolbar.addHandler('video', function () {
        const range = quill.getSelection(true);
        if (!range) {
            return;
        }
        const input = window.prompt(options.labels.videoUrl, 'https://');
        if (input === null) {
            return;
        }
        const embedUrl = admidioQuillVideoUrl(input);
        if (!embedUrl) {
            window.alert(options.labels.invalidVideoUrl);
            return;
        }
        quill.insertEmbed(range.index, 'video', embedUrl, 'user');
        quill.setSelection(range.index + 1, 0, 'silent');
    });

    toolbar.addHandler('image', function () {
        const range = quill.getSelection(true);
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.style.display = 'none';
        document.body.appendChild(fileInput);
        fileInput.addEventListener('change', async function () {
            const file = fileInput.files[0];
            fileInput.remove();
            if (!file) {
                return;
            }

            const submitButtons = Array.from(form.querySelectorAll('[type="submit"]'))
                .map(function (button) { return { button: button, disabled: button.disabled }; });
            submitButtons.forEach(function (entry) { entry.button.disabled = true; });
            const data = new FormData();
            data.append('upload', file);
            try {
                const response = await fetch(options.uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': options.csrfToken },
                    body: data
                });
                if (!response.ok) {
                    throw new Error('Image upload failed');
                }
                const result = await response.json();
                if (!result.url) {
                    throw new Error('Image upload returned no URL');
                }
                quill.insertEmbed(range ? range.index : quill.getLength() - 1, 'image', result.url, 'user');
            } catch (error) {
                console.error(error);
                const alert = form.querySelector('.form-alert');
                if (alert) {
                    alert.className = 'alert alert-danger form-alert';
                    alert.textContent = options.uploadError;
                    alert.style.display = '';
                }
            } finally {
                submitButtons.forEach(function (entry) { entry.button.disabled = entry.disabled; });
            }
        });
        fileInput.click();
    });
}

/** Convert public YouTube and Vimeo links into the two iframe URLs permitted on save. */
function admidioQuillVideoUrl(input) {
    let url;
    try {
        url = new URL(input);
    } catch (error) {
        return null;
    }
    if (url.protocol !== 'https:' && url.protocol !== 'http:') {
        return null;
    }

    const host = url.hostname.toLowerCase();
    let videoId = null;
    if (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'].includes(host)) {
        if (url.pathname === '/watch') {
            videoId = url.searchParams.get('v');
        } else {
            const match = url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})\/?$/);
            videoId = match ? match[1] : null;
        }
    } else if (host === 'youtu.be' || host === 'www.youtu.be') {
        const match = url.pathname.match(/^\/([A-Za-z0-9_-]{11})\/?$/);
        videoId = match ? match[1] : null;
    }
    if (videoId && /^[A-Za-z0-9_-]{11}$/.test(videoId)) {
        return 'https://www.youtube-nocookie.com/embed/' + videoId;
    }

    if (['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'].includes(host)) {
        const match = url.pathname.match(/^\/(?:video\/)?([0-9]+)\/?$/);
        if (match) {
            return 'https://player.vimeo.com/video/' + match[1];
        }
    }
    return null;
}
