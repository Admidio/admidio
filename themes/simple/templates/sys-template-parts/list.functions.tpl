{* Show up to two actions directly on all screens; additional actions use a responsive menu. *}
{if {array_key_exists array=$data key='actions'} && count($data.actions) > 0}
    {assign var=actionCount value=count($data.actions)}
    <div class="admidio-functions d-inline-flex align-items-center text-nowrap">
        {foreach $data.actions as $actionItem}
            {if $actionCount <= 2 || $actionItem@first}
            {assign var=actionClass value='admidio-icon-link'}
            {if $actionCount > 2}{assign var=actionClass value='d-none d-md-inline-flex admidio-icon-link'}{/if}
            {include 'sys-template-parts/list.action.tpl' actionItem=$actionItem actionClass=$actionClass}
            {/if}
        {/foreach}
        {if $actionCount > 2}
        <div class="dropdown">
            <a id="adm_dropdown_menu_button_{$data.uuid}" class="admidio-icon-link" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{$l10n->get('SYS_MORE')}">
                <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adm_dropdown_menu_button_{$data.uuid}">
                {foreach $data.actions as $actionItem}
                    <li{if $actionItem@first} class="d-md-none"{/if}>
                        {include 'sys-template-parts/list.action.tpl' actionItem=$actionItem actionClass='dropdown-item'}
                    </li>
                {/foreach}
            </ul>
        </div>
        {/if}
    </div>
{/if}
