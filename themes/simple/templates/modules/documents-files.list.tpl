{if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if}


    <table id="adm_documents_files_table" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th><i class="bi bi-folder-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th style="word-break: break-word;">{$l10n->get('SYS_DATE_MODIFIED')}</th>
                <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                <th class="text-end">{$l10n->get('SYS_COUNTER')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            {foreach $list as $row}
                <tr id="row_{$row.uuid}">
                    <td><i class="{$row.icon}" data-bs-toggle="tooltip" title="{$row.title}"></i></td>
                    <td style="word-break: break-word;"><a href="{$row.url}">{$row.name}</a>
                        {if strlen($row.description) > 0}
                            <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                title="{$l10n->get('SYS_DESCRIPTION')}" data-bs-content="{$row.description}"></i>
                        {/if}
                    </td>
                    <td>{$row.timestamp}</td>
                    <td class="text-end">{$row.size}</td>
                    <td class="text-end">{$row.counter}</td>
                    <td class="text-end">
                        {include 'sys-template-parts/list.functions.tpl' data=$row}

                        {if $row.existsInFileSystem == false}
                            <i class="bi bi-exclamation-triangle-fill" style="color:red;" data-bs-toggle="popover" data-bs-trigger="hover click" data-bs-placement="left"
                               title="{$l10n->get('SYS_WARNING')}" data-bs-content="{if $row.folder}{$l10n->get('SYS_FOLDER_NOT_EXISTS')}{else}{$l10n->get('SYS_FILE_NOT_EXIST_DELETE_FROM_DB')}{/if}"></i>
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>


{if count($unregisteredList) > 0}
    <h2>{$l10n->get('SYS_UNMANAGED_FILES')}</h2>
    <p class="lead">{$l10n->get('SYS_ADDITIONAL_FILES')}</p>
    <div class="table-responsive">
        <table id="documents-files-unregistered-table" class="table table-hover" width="100%" style="width: 100%;">
            <thead>
            <tr>
                <th><i class="bi bi-folder-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            {foreach $unregisteredList as $row}
                <tr>
                    <td><i class="{$row.icon}" data-bs-toggle="tooltip" title="{$row.title}"></i></td>
                    <td>{$row.name}</td>
                    <td class="text-end">{$row.size}</td>
                    <td class="text-end">
                        <a class="admidio-icon-link admidio-send-csrf-token" data-url="{$row.url}" data-csrf-token="{$row.csrfToken}">
                            <i class="bi bi-plus-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ADD_TO_DATABASE')}"></i>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
{/if}

<!-- In-App Media & Document Preview Modal for Documents & Files (Prevents PWA Trapping) -->
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
            <div class="modal-footer d-flex justify-content-between">
                <a id="adm_file_preview_download_btn" href="#" download class="btn btn-outline-primary">
                    <i class="bi bi-download me-1"></i> {$l10n->get('SYS_DOWNLOAD_FILE')}
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
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
    const newWindowLabel = (previewModalEl.dataset && previewModalEl.dataset.labelNewWindow)
        ? previewModalEl.dataset.labelNewWindow
        : 'New window';

    // Clean up media when modal is hidden
    previewModalEl.addEventListener('hidden.bs.modal', function () {
        const media = previewBody.querySelector('video, audio');
        if (media) {
            media.pause();
            media.removeAttribute('src');
            media.load();
        }
        previewBody.innerHTML = '';
    });

    const isPreviewableFile = function (ext) {
        const previewExts = ['mp4', 'webm', 'mov', 'm4v', 'ogv', 'mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];
        return previewExts.includes(ext);
    };

    // Ensure non-previewable files (archives, office docs) always open in a separate window so the main PWA stays open
    const applyTargetBlankToNonPreview = function () {
        const fileLinks = document.querySelectorAll('#adm_documents_files_table tbody td a[href*="mode=download"]');
        fileLinks.forEach(function (link) {
            const filename = link.textContent.trim();
            const extMatch = filename.match(/\.([0-9a-z]+)$/i);
            const ext = extMatch ? extMatch[1].toLowerCase() : '';
            if (!isPreviewableFile(ext)) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            }
        });
    };

    applyTargetBlankToNonPreview();

    // Intercept clicks on media & document file links in the table
    const tableEl = document.getElementById('adm_documents_files_table');
    if (tableEl) {
        if (window.jQuery) {
            window.jQuery(tableEl).on('draw.dt', applyTargetBlankToNonPreview);
        }

        tableEl.addEventListener('click', function (e) {
            const link = e.target.closest('tbody td a[href*="mode=download"]');
            if (!link) return;

            const filename = link.textContent.trim();
            const extMatch = filename.match(/\.([0-9a-z]+)$/i);
            const ext = extMatch ? extMatch[1].toLowerCase() : '';

            const isVideo = ['mp4', 'webm', 'mov', 'm4v', 'ogv'].includes(ext);
            const isAudio = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'].includes(ext);
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
            const isPdf = (ext === 'pdf');

            if (isVideo || isAudio || isImage || isPdf) {
                e.preventDefault();
                e.stopPropagation();

                previewTitle.textContent = filename;
                const rawDownloadUrl = link.href.replace(/&view=1/g, '');
                previewDownloadBtn.href = rawDownloadUrl;
                previewDownloadBtn.setAttribute('download', filename);

                if (isVideo) {
                    previewIcon.className = 'bi bi-camera-video-fill text-danger me-2';
                    previewBody.parentElement.className = 'modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center';
                    previewBody.innerHTML = '<video controls autoplay playsinline class="w-100" style="max-height: 70vh; display: block; background: #000;" src="' + link.href + '"></video>';
                } else if (isAudio) {
                    previewIcon.className = 'bi bi-music-note-beamed text-primary me-2';
                    previewBody.parentElement.className = 'modal-body p-4 text-center bg-light';
                    previewBody.innerHTML = '<div class="my-3"><i class="bi bi-music-note-beamed fs-1 text-primary d-block mb-3"></i><audio controls autoplay style="width: 100%; max-width: 500px;" src="' + link.href + '"></audio></div>';
                } else if (isImage) {
                    previewIcon.className = 'bi bi-image-fill text-success me-2';
                    previewBody.parentElement.className = 'modal-body p-2 text-center bg-dark d-flex align-items-center justify-content-center';
                    previewBody.innerHTML = '<img src="' + link.href + '" alt="' + filename + '" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;">';
                } else if (isPdf) {
                    previewIcon.className = 'bi bi-file-earmark-pdf-fill text-danger me-2';
                    previewBody.parentElement.className = 'modal-body p-0 text-center bg-dark';
                    previewBody.innerHTML = '<iframe src="' + link.href + '" style="width: 100%; height: clamp(320px, 55vh, 700px); border: none; display: block;" title="' + filename + '"></iframe>' +
                        '<div class="p-2 bg-light border-top d-flex justify-content-center">' +
                        '<a href="' + link.href + '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary" title="' + newWindowLabel + '">' +
                        '<i class="bi bi-box-arrow-up-right me-1"></i> ' + newWindowLabel +
                        '</a></div>';
                }

                previewModal.show();
            } else {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            }
        });
    }
});
</script>
{/literal}
