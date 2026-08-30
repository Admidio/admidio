{*
    Create the functions menu out of the menu array.

    One action is rendered exactly like several. Rendering a lone action differently used to leave it
    outside admidio-functions, so it stayed visible while a row next to it with two actions only
    showed them on hover - and a list whose rows offer different numbers of actions looked broken.
*}
{if {array_key_exists array=$data key='actions'}}
    <div class="d-none d-lg-inline admidio-functions">
        {foreach $data.actions as $actionItem}
            <a {if isset($actionItem.popup)} class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-class="{$actionItem.popupClass|default:'modal-lg'}" data-href="{$actionItem.dataHref}"
                    {elseif isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                    {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
        {/foreach}
    </div>
    <div class="dropdown d-lg-none">
        <a id="adm_dropdown_menu_button_{$data.uuid}" class="admidio-icon-link" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static">
            <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adm_dropdown_menu_button_{$data.uuid}">
            {foreach $data.actions as $actionItem}
                <li>
                    <a {if isset($actionItem.popup)} class="dropdown-item openPopup" href="javascript:void(0);"
                        data-class="{$actionItem.popupClass|default:'modal-lg'}" data-href="{$actionItem.dataHref}"
                            {elseif isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                        data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                            {else} class="dropdown-item" href="{$actionItem.url}"{/if}>
                        <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                </li>
            {/foreach}
        </ul>
    </div>
{/if}
