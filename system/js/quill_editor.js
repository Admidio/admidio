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
        container.style.display = '';
        quill = new Quill(container, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image'],
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
        textarea.value = quill.getText().trim() === '' && !quill.root.querySelector('img')
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
    const buttons = {
        bold: 'button.ql-bold',
        italic: 'button.ql-italic',
        underline: 'button.ql-underline',
        ordered: 'button.ql-list[value="ordered"]',
        bullet: 'button.ql-list[value="bullet"]',
        link: 'button.ql-link',
        image: 'button.ql-image',
        clean: 'button.ql-clean'
    };
    Object.entries(buttons).forEach(function ([name, selector]) {
        const button = toolbar.container.querySelector(selector);
        if (button) {
            button.setAttribute('aria-label', options.labels[name]);
            button.setAttribute('title', options.labels[name]);
        }
    });

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
