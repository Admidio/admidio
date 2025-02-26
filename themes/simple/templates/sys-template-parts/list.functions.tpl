{* Create the functions menu out of the menu array *}
{if {array_key_exists array=$data key='actions'}}
    {if count($data.actions) eq 1}
        {foreach $data.actions as $actionItem}
            <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                    {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
        {/foreach}
    {else}
        <div class="d-none d-lg-inline admidio-functions">
            {foreach $data.actions as $actionItem}
                <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
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
                        <a {if isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                            data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                {else} class="dropdown-item" href="{$actionItem.url}"{/if}>
                            <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                    </li>
                {/foreach}
            </ul>
        </div>
    {/if}
{/if}
