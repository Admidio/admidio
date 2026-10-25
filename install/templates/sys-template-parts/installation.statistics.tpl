    <div class="d-flex align-items-center gap-2 mb-3 position-relative">
        <input id="adm_send_statistics_summary" class="form-check-input m-0" type="checkbox" {if $statisticsEnabled|default:true}checked{/if} disabled
            aria-label="{$l10n->get('SYS_SEND_ANONYMOUS_STATISTICS')|escape:'html'}">
        <a href="#adm_statistics_modal" class="stretched-link" data-bs-toggle="modal" aria-controls="adm_statistics_modal" aria-haspopup="dialog">
            {$l10n->get('SYS_SEND_ANONYMOUS_STATISTICS')}
        </a>
    </div>

    {include 'sys-template-parts/installation-statistics-modal.tpl'}
