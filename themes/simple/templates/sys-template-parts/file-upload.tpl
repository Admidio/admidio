{* Upload of files or photos: drop zone, correction of invalid file names and progress. The script is
   system/js/file-upload.js, see FileUpload::getHtml() *}
{if !$isModal}
    <p class="lead">{$textUploadDescription}</p>
{/if}

<div id="admidio-dropzone" class="admidio-dropzone bg-body-tertiary rounded-3 text-center px-4 py-5 mb-4" tabindex="0" role="region" aria-label="{$textDragAndDrop}">
    <div class="admidio-dropzone-content">
        <i class="bi bi-cloud-arrow-up admidio-dropzone-icon d-inline-block text-primary mb-2"></i>
        <div class="fs-5 fw-semibold mb-1">{$textDragAndDrop}</div>
        <div class="small text-uppercase text-body-secondary my-2">{$l10n->get('SYS_OR')}</div>
        <span class="btn btn-primary fileinput-button">
            <i class="bi bi-upload me-2"></i>{$textSelectFiles}
            <input id="fileupload" type="file" name="files[]" multiple>
        </span>
    </div>
</div>

<div id="admidio-upload-invalid-container" class="card shadow-sm mb-4 d-none">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <i class="bi bi-pencil-square text-primary me-2"></i>
            <span>{$l10n->get('SYS_INVALID_FILENAMES_DETECTED')}</span>
        </h5>
        <button type="button" class="btn-close" id="btn-close-invalid" aria-label="{$l10n->get('SYS_CANCEL_ALL')}"></button>
    </div>
    <div class="card-body">
        <p class="card-text text-muted mb-3">{$l10n->get('SYS_INVALID_FILENAMES_DESC')}</p>
        <div id="admidio-upload-invalid-list" class="list-group list-group-flush"></div>
    </div>
    <div class="card-footer bg-body-tertiary d-flex flex-wrap justify-content-end align-items-center gap-2">
        <button id="btn-cancel-pending" type="button" class="btn btn-secondary">
            <i class="bi bi-x-circle me-1"></i>{$l10n->get('SYS_CANCEL_ALL')}
        </button>
        <button id="btn-sanitize-all" type="button" class="btn btn-primary">
            <i class="bi bi-check2-circle me-1"></i>{$l10n->get('SYS_APPLY_ALL_AND_UPLOAD')}
        </button>
    </div>
</div>

<div id="progress-wrapper" class="mb-4 d-none">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span id="upload-status-text" class="text-muted small"></span>
        <button id="btn-cancel-all-uploads" type="button" class="btn btn-sm btn-outline-danger py-0 px-2" title="{$l10n->get('SYS_UPLOAD_CANCEL')}">
            <i class="bi bi-x-circle me-1"></i>{$l10n->get('SYS_UPLOAD_CANCEL')}
        </button>
    </div>
    <div id="progress" class="progress">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"></div>
    </div>
</div>
<div id="files" class="files mb-3"></div>

{if !$isModal}
    <button id="back" class="btn btn-secondary admidio-margin-bottom d-none">{$textBackButton}</button>
{/if}
