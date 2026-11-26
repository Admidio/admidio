{if !($statisticsAlreadyEnabled|default:false)}
    {if $statisticsInline|default:false}
        <div class="d-flex align-items-baseline gap-2 mb-3">
            <input id="adm_send_statistics" name="adm_send_statistics" class="form-check-input flex-shrink-0 m-0" type="checkbox" value="1"
                {foreach $elements['adm_send_statistics']['attributes'] as $attribute}
                    {$attribute@key}="{$attribute}"
                {/foreach}
            >
            <label class="form-check-label" for="adm_send_statistics">
                <a href="#adm_statistics_modal" data-bs-toggle="modal" aria-controls="adm_statistics_modal" aria-haspopup="dialog">
                    {$l10n->get('SYS_SEND_PSEUDONYMOUS_STATISTICS')}
                </a>
            </label>
        </div>
    {else}
    <div class="d-flex align-items-center gap-2 mb-3 position-relative">
        <input id="adm_send_statistics_summary" class="form-check-input m-0" type="checkbox" {if $statisticsEnabled|default:true}checked{/if} disabled
            aria-label="{$l10n->get('SYS_SEND_PSEUDONYMOUS_STATISTICS')|escape:'html'}">
        <a href="#adm_statistics_modal" class="stretched-link" data-bs-toggle="modal" aria-controls="adm_statistics_modal" aria-haspopup="dialog">
            {$l10n->get('SYS_SEND_PSEUDONYMOUS_STATISTICS')}
        </a>
    </div>

    {/if}
    {if $statisticsInline|default:false}
        {include 'sys-template-parts/installation-statistics-modal.tpl' statisticsShowCheckbox=false}
    {else}
        {include 'sys-template-parts/installation-statistics-modal.tpl'}
    {/if}
{/if}
