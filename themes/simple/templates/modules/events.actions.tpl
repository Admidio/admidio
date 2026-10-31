<div class="col-auto ms-auto text-nowrap">
    {foreach ['ical', 'copy', 'edit', 'delete'] as $actionKey}
        {if isset($actions[$actionKey])}
            {assign var=actionItem value=$actions[$actionKey]}
            <a class="admidio-icon-link d-inline-flex p-1 fs-6{if isset($actionItem.popup)} openPopup{elseif isset($actionItem.dataHref)} admidio-messagebox{/if}"
                {if isset($actionItem.popup)} href="javascript:void(0);" data-href="{$actionItem.dataHref}"
                {elseif isset($actionItem.dataHref)} href="javascript:void(0);" data-buttons="yes-no" data-message="{$actionItem.dataMessage|escape:'html'}" data-href="{$actionItem.dataHref}"
                {else} href="{$actionItem.url}"{/if}
                aria-label="{$actionItem.tooltip|escape:'html'}"><i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip|escape:'html'}"></i></a>
        {/if}
    {/foreach}
</div>
