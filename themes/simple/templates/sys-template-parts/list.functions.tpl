{* Show up to two actions directly on all screens; additional actions use a responsive menu. *}
{if {array_key_exists array=$data key='actions'} && count($data.actions) > 0}
    {assign var=actionCount value=count($data.actions)}
    <div class="admidio-functions d-inline-flex align-items-center text-nowrap">
        {foreach $data.actions as $actionItem}
            {if $actionCount <= 2 || $actionItem@first}
            <a {if isset($actionItem.popup)} class="{if $actionCount > 2}d-none d-md-inline-flex {/if}admidio-icon-link openPopup" href="javascript:void(0);"
                data-class="{$actionItem.popupClass|default:'modal-lg'}" data-href="{$actionItem.dataHref}"
                    {elseif isset($actionItem.dataHref)} class="{if $actionCount > 2}d-none d-md-inline-flex {/if}admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                data-buttons="yes-no" data-message="{$actionItem.dataMessage|escape:'html'}" data-href="{$actionItem.dataHref}"
                    {else} class="{if $actionCount > 2}d-none d-md-inline-flex {/if}admidio-icon-link{if isset($actionItem.class)} {$actionItem.class}{/if}" href="{$actionItem.url}"{/if}>
                <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i><span class="visually-hidden">{$actionItem.tooltip|escape:'html'}</span></a>
            {/if}
        {/foreach}
        {if $actionCount > 2}
        <div class="dropdown">
            <a id="adm_dropdown_menu_button_{$data.uuid}" class="admidio-icon-link" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{$l10n->get('SYS_MORE')}">
                <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adm_dropdown_menu_button_{$data.uuid}">
                {foreach $data.actions as $actionItem}
                    <li{if $actionItem@first} class="d-md-none"{/if}>
                        <a {if isset($actionItem.popup)} class="dropdown-item openPopup" href="javascript:void(0);"
                            data-class="{$actionItem.popupClass|default:'modal-lg'}" data-href="{$actionItem.dataHref}"
                                {elseif isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                            data-buttons="yes-no" data-message="{$actionItem.dataMessage|escape:'html'}" data-href="{$actionItem.dataHref}"
                                {else} class="dropdown-item{if isset($actionItem.class)} {$actionItem.class}{/if}" href="{$actionItem.url}"{/if}>
                            <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                    </li>
                {/foreach}
            </ul>
        </div>
        {/if}
    </div>
{/if}
