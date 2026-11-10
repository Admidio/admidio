{* Lists show at most two icons; cards show all icons from md and use a menu on phones. *}
{assign var=isCard value=$cardActions|default:false}
{* Plugins may change the actions of a list through the list_row_actions hook, which needs the listId. *}
{list_actions actions=$data.actions|default:[] listId=$listId|default:'' row=$listRow|default:$data assign='rowActions'}
{if count($rowActions) > 0}
    {assign var=actionCount value=count($rowActions)}
    <div class="admidio-functions d-inline-flex align-items-center text-nowrap">
        {foreach $rowActions as $actionItem}
            {if $isCard || $actionCount <= 2 || $actionItem@first}
            {assign var=actionClass value='admidio-icon-link'}
            {if $isCard || $actionCount > 2}{assign var=actionClass value='d-none d-md-inline-flex admidio-icon-link'}{/if}
            {include 'sys-template-parts/list.action.tpl' actionItem=$actionItem actionClass=$actionClass}
            {/if}
        {/foreach}
        {if $isCard || $actionCount > 2}
        <div class="dropdown{if $isCard} d-md-none{/if}">
            <a id="adm_dropdown_menu_button_{$data.uuid}" class="admidio-icon-link" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{$l10n->get('SYS_MORE')}">
                <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adm_dropdown_menu_button_{$data.uuid}">
                {foreach $rowActions as $actionItem}
                    <li{if !$isCard && $actionItem@first} class="d-md-none"{/if}>
                        {include 'sys-template-parts/list.action.tpl' actionItem=$actionItem actionClass='dropdown-item'}
                    </li>
                {/foreach}
            </ul>
        </div>
        {/if}
    </div>
{/if}
