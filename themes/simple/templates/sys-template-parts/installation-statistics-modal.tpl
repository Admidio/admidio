    <div class="modal fade" id="adm_statistics_modal" tabindex="-1" aria-labelledby="adm_statistics_title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="adm_statistics_title">{$l10n->get('SYS_SEND_ANONYMOUS_STATISTICS')}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CLOSE')|escape:'html'}"></button>
                </div>
                <div class="modal-body">
                    <p>{$l10n->get('SYS_ANONYMOUS_STATISTICS_DESC')}
                        {if $statisticsShowCheckbox|default:true}{$l10n->get('SYS_ANONYMOUS_STATISTICS_CONSENT_DESC')}{/if}</p>
                    <pre class="bg-body-tertiary border rounded p-3"><code>{$statisticsJson|escape:'html'}</code></pre>
                    {if $statisticsShowCheckbox|default:true}
                        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['adm_send_statistics']}
                    {/if}
                </div>
            </div>
        </div>
    </div>
