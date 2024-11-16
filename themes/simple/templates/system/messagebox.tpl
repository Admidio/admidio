<div id="adm_modal_messagebox" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{$l10n->get('SYS_NOTE')}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p></p>
            </div>
            <div class="modal-footer">
                <a id="adm_messagebox_button_yes" type="button" class="btn btn-primary">{$l10n->get('SYS_YES')}</a>
                <a id="adm_messagebox_button_no" type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$l10n->get('SYS_NO')}</a>
                <div id="adm_status_message" class="mt-4 w-100"></div>
            </div>
        </div>
    </div>
</div>
