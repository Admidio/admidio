{*
    In-App Media & Document Preview Modal (Prevents PWA Trapping)
    This template is included once in index.tpl. Every link with the attribute data-adm-file-preview
    and every link to documents-files.php?mode=download within user-written content (announcements,
    events, forum ...) is handled by this modal. The file type is determined by the extension of the
    attribute data-file-name or, if this attribute is not set, of the link text. Previewable files
    (video, audio, image, pdf) are opened within this modal, all other files and links whose text has
    no file extension are opened in a new window. The file url must support the parameter view=1 to
    show the file inline.
*}
<div class="modal fade" id="adm_file_preview_modal" tabindex="-1" aria-labelledby="adm_file_preview_title" aria-hidden="true" data-label-new-window="{$l10n->get('SYS_NEW_WINDOW')}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-truncate" id="adm_file_preview_title">
                    <i id="adm_file_preview_icon" class="bi bi-file-earmark me-2"></i>
                    <span id="adm_file_preview_filename">{$l10n->get('SYS_PREVIEW')}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center" style="min-height: 200px;">
                <div id="adm_file_preview_body" class="w-100">
                    <!-- Populated dynamically with <video>, <audio>, <img>, or <iframe> -->
                </div>
            </div>
            <div class="modal-footer">
                <a id="adm_file_preview_download_btn" href="#" download class="btn btn-outline-primary col-12 col-lg-auto">
                    <i class="bi bi-download me-1"></i> {$l10n->get('SYS_DOWNLOAD_FILE')}
                </a>
                <button id="adm_file_preview_share_btn" type="button" class="btn btn-outline-secondary col-12 col-lg-auto"
                    data-label-share-link="{$l10n->get('SYS_SHARE_LINK')}" data-label-copy-link="{$l10n->get('SYS_COPY_LINK')}"
                    data-label-copied="{$l10n->get('SYS_COPIED_CLIPBOARD')}">
                    <i class="bi bi-link-45deg me-1"></i> <span>{$l10n->get('SYS_COPY_LINK')}</span>
                </button>
                <button type="button" class="btn btn-secondary col-12 col-lg-auto ms-lg-auto" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> {$l10n->get('SYS_CLOSE')}
                </button>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewModalEl = document.getElementById('adm_file_preview_modal');
    if (!previewModalEl) return;

    const previewModal = new bootstrap.Modal(previewModalEl);
    const previewTitle = document.getElementById('adm_file_preview_filename');
    const previewIcon = document.getElementById('adm_file_preview_icon');
    const previewBody = document.getElementById('adm_file_preview_body');
    const previewDownloadBtn = document.getElementById('adm_file_preview_download_btn');
    const previewShareBtn = document.getElementById('adm_file_preview_share_btn');
    const previewShareIcon = previewShareBtn.querySelector('i');
    const previewShareText = previewShareBtn.querySelector('span');
    let previewShareUrl = '';

    // share the link with the share dialog of the system, or copy it if the browser can't share links.
    // If the browser still uses an older cached common_functions.js without these functions, the button
    // is hidden, so the preview itself still works.
    if (typeof shareOrCopyLink !== 'function' || typeof canShareLink !== 'function') {
        previewShareBtn.classList.add('d-none');
    } else {
        const shareIconClass = canShareLink() ? 'bi bi-share me-1' : 'bi bi-link-45deg me-1';
        const shareText = canShareLink() ? previewShareBtn.dataset.labelShareLink : previewShareBtn.dataset.labelCopyLink;
        previewShareIcon.className = shareIconClass;
        previewShareText.textContent = shareText;
        previewShareBtn.addEventListener('click', function () {
            shareOrCopyLink(previewShareUrl, previewTitle.textContent, previewShareBtn.dataset.labelCopyLink).then(function (result) {
                if (result === 'copied') {
                    previewShareIcon.className = 'bi bi-clipboard-check me-1';
                    previewShareText.textContent = previewShareBtn.dataset.labelCopied;
                    setTimeout(function () {
                        previewShareIcon.className = shareIconClass;
                        previewShareText.textContent = shareText;
                    }, 2000);
                }
            });
        });
    }

    const newWindowLabel = (previewModalEl.dataset && previewModalEl.dataset.labelNewWindow)
        ? previewModalEl.dataset.labelNewWindow
        : 'New window';

    const videoExts = ['mp4', 'webm', 'mov', 'm4v', 'ogv'];
    const audioExts = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'];
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    // Clean up media when modal is hidden
    previewModalEl.addEventListener('hidden.bs.modal', function () {
        const media = previewBody.querySelector('video, audio');
        if (media) {
            media.pause();
            media.removeAttribute('src');
            media.load();
        }
        previewBody.replaceChildren();
    });

    const createElement = function (tag, attributes, style) {
        const element = document.createElement(tag);
        Object.keys(attributes).forEach(function (name) {
            element.setAttribute(name, attributes[name]);
        });
        if (style) {
            element.style.cssText = style;
        }
        return element;
    };

    // Find the clicked file link. Marked links (documents module, overview plugin, message attachments,
    // changelog) are always handled. Unmarked links to the documents module are typically inserted by users
    // within the editor. Ignore the action icons of the documents list and the links within this modal.
    const getFileLink = function (target) {
        const markedLink = target.closest('a[data-adm-file-preview]');
        if (markedLink) {
            return markedLink;
        }

        const documentLink = target.closest('a[href*="documents-files.php?mode=download"]');
        if (documentLink && !documentLink.hasAttribute('download')
            && !documentLink.matches('.admidio-icon-link, .dropdown-item')
            && !documentLink.closest('#adm_file_preview_modal')) {
            return documentLink;
        }
        return null;
    };

    // Intercept clicks on all file links that could be shown in the preview
    document.addEventListener('click', function (e) {
        // let the browser handle clicks that should open a new tab or window
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        const link = getFileLink(e.target);
        if (!link) return;

        const filename = (link.dataset.fileName || link.textContent).trim();
        const extMatch = filename.match(/\.([0-9a-z]+)$/i);
        const ext = extMatch ? extMatch[1].toLowerCase() : '';

        const isVideo = videoExts.includes(ext);
        const isAudio = audioExts.includes(ext);
        const isImage = imageExts.includes(ext);
        // browsers without an own pdf viewer (e.g. Chrome on Android) can't show a pdf within the iframe
        const isPdf = (ext === 'pdf' && navigator.pdfViewerEnabled !== false);

        if (!(isVideo || isAudio || isImage || isPdf)) {
            // Ensure non-previewable files (archives, office docs) open in a separate window so the main PWA stays open
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        // hide a tooltip of the link, otherwise it will stay visible above the modal
        const tooltip = bootstrap.Tooltip.getInstance(link);
        if (tooltip) {
            tooltip.hide();
        }

        // the preview needs the inline view of the file, the download button the attachment
        const downloadUrl = link.href.replace(/&view=1/g, '');
        const viewUrl = downloadUrl + '&view=1';

        previewTitle.textContent = filename;
        previewDownloadBtn.href = downloadUrl;
        previewShareUrl = viewUrl;
        previewDownloadBtn.setAttribute('download', filename);
        previewBody.replaceChildren();

        if (isVideo) {
            previewIcon.className = 'bi bi-camera-video-fill text-danger me-2';
            previewBody.parentElement.className = 'modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center';
            previewBody.append(createElement('video', {controls: '', autoplay: '', playsinline: '', class: 'w-100', src: viewUrl},
                'max-height: 70vh; display: block; background: #000;'));
        } else if (isAudio) {
            previewIcon.className = 'bi bi-music-note-beamed text-primary me-2';
            previewBody.parentElement.className = 'modal-body p-4 text-center bg-light';
            const wrapper = createElement('div', {class: 'my-3'});
            wrapper.append(
                createElement('i', {class: 'bi bi-music-note-beamed fs-1 text-primary d-block mb-3'}),
                createElement('audio', {controls: '', autoplay: '', src: viewUrl}, 'width: 100%; max-width: 500px;')
            );
            previewBody.append(wrapper);
        } else if (isImage) {
            previewIcon.className = 'bi bi-image-fill text-success me-2';
            previewBody.parentElement.className = 'modal-body p-2 text-center bg-dark d-flex align-items-center justify-content-center';
            previewBody.append(createElement('img', {src: viewUrl, alt: filename, class: 'img-fluid rounded'},
                'max-height: 75vh; object-fit: contain;'));
        } else if (isPdf) {
            previewIcon.className = 'bi bi-file-earmark-pdf-fill text-danger me-2';
            previewBody.parentElement.className = 'modal-body p-0 text-center bg-dark';
            const buttonBar = createElement('div', {class: 'p-2 bg-light border-top d-flex justify-content-center'});
            const newWindowButton = createElement('a', {href: viewUrl, target: '_blank', rel: 'noopener noreferrer',
                class: 'btn btn-sm btn-outline-secondary', title: newWindowLabel});
            newWindowButton.append(createElement('i', {class: 'bi bi-box-arrow-up-right me-1'}), ' ' + newWindowLabel);
            buttonBar.append(newWindowButton);
            previewBody.append(
                createElement('iframe', {src: viewUrl, title: filename},
                    'width: 100%; height: clamp(320px, 55vh, 700px); border: none; display: block;'),
                buttonBar
            );
        }

        previewModal.show();
    });
});
</script>
{/literal}
