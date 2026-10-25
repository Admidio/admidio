    <div class="d-flex align-items-center gap-2 mb-3 position-relative">
        <input id="adm_send_statistics_summary" class="form-check-input m-0" type="checkbox" checked disabled
            aria-label="{$l10n->get('SYS_SEND_ANONYMOUS_STATISTICS')|escape:'html'}">
        <a href="#adm_statistics_modal" class="stretched-link" data-bs-toggle="modal" aria-controls="adm_statistics_modal" aria-haspopup="dialog">
            {$l10n->get('SYS_SEND_ANONYMOUS_STATISTICS')}
        </a>
    </div>

    <div class="modal fade" id="adm_statistics_modal" tabindex="-1" aria-labelledby="adm_statistics_title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="adm_statistics_title">{$l10n->get('SYS_SEND_ANONYMOUS_STATISTICS')}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CLOSE')|escape:'html'}"></button>
                </div>
                <div class="modal-body">
                    <p>{$l10n->get('SYS_ANONYMOUS_STATISTICS_DESC')}</p>
                    <pre class="bg-body-tertiary border rounded p-3"><code>{$statisticsJson|escape:'html'}</code></pre>
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['adm_send_statistics']}
                </div>
            </div>
        </div>
    </div>
