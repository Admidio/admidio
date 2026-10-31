{* Upload dialog of a folder page and the overlay that is shown when files are dragged over the page,
   see FileUpload::getModalHtml() *}
<div id="admidio-folder-drop-overlay" class="admidio-folder-drop-overlay position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center d-none">
    <div class="admidio-folder-drop-overlay-box bg-body text-body rounded-4 shadow-lg text-center px-5 py-5">
        <i class="bi bi-cloud-arrow-up-fill d-block display-4 text-primary mb-3"></i>
        <h3 class="fw-semibold mb-2">{$textDragAndDrop}</h3>
        <p class="lead text-body-secondary mb-0">{$destinationName}</p>
    </div>
</div>

<div class="modal fade" id="admidio-upload-modal" tabindex="-1" aria-labelledby="admidio-upload-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center" id="admidio-upload-modal-title">
                    <i class="bi bi-cloud-arrow-up text-primary me-2"></i>
                    <span>{$headline} - {$destinationName}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
            </div>
            <div class="modal-body">
                {include file="sys-template-parts/file-upload.tpl"}
            </div>
        </div>
    </div>
</div>
