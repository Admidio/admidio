{* The items plugins add to the header bar through the filter page_header_items (see PageExtensions).
   An item without a class of its own takes the colors of the header bar. *}
{if !empty($headerItems)}{foreach $headerItems as $headerItem}
    {if $headerItem.class == ''}{$headerItemClass='admidio-header-item-default'}{else}{$headerItemClass=$headerItem.class}{/if}
    {if count($headerItem.items) > 0}
        <span id="adm_header_item_{$headerItem.id}" class="dropdown me-2">
            <a class="btn btn-sm dropdown-toggle admidio-header-item {$headerItemClass}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"{if $headerItem.tooltip != ''} title="{$headerItem.tooltip|escape}"{/if}>
                {if $headerItem.image != ''}<img class="admidio-header-item-image" src="{$headerItem.image|escape}" alt="">{elseif $headerItem.icon != ''}<i class="{$headerItem.icon}"></i>{/if} <span class="d-none d-sm-inline">{$headerItem.label|escape}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end admidio-header-item-menu">
                {foreach $headerItem.items as $child}
                    {if $child.divider}
                        <li><hr class="dropdown-divider"></li>
                    {elseif $child.header}
                        <li><h6 class="dropdown-header">{$child.label|escape}</h6></li>
                    {else}
                        <li><a id="adm_header_item_{$headerItem.id}_{$child.id}" class="dropdown-item{if $child.active} active{/if}{if $child.popup} openPopup{/if} {$child.class}" {if $child.popup}href="javascript:void(0);" data-href="{$child.url|escape}"{else}href="{$child.url|escape}"{/if}{if $child.tooltip != ''} title="{$child.tooltip|escape}"{/if}{if $child.active} aria-current="true"{/if}>
                            {if $child.image != ''}<img class="admidio-header-item-image me-1" src="{$child.image|escape}" alt=""> {elseif $child.icon != ''}<i class="{$child.icon}"></i> {/if}{$child.label|escape}</a></li>
                    {/if}
                {/foreach}
            </ul>
        </span>
    {elseif $headerItem.url != ''}
        <a id="adm_header_item_{$headerItem.id}" class="btn btn-sm me-2 admidio-header-item {$headerItemClass}{if $headerItem.popup} openPopup{/if}" {if $headerItem.popup}href="javascript:void(0);" data-href="{$headerItem.url|escape}"{else}href="{$headerItem.url|escape}"{/if}{if $headerItem.tooltip != ''} title="{$headerItem.tooltip|escape}"{/if}>
            {if $headerItem.image != ''}<img class="admidio-header-item-image" src="{$headerItem.image|escape}" alt="">{elseif $headerItem.icon != ''}<i class="{$headerItem.icon}"></i>{/if} <span class="d-none d-sm-inline">{$headerItem.label|escape}</span>
        </a>
    {else}
        <span id="adm_header_item_{$headerItem.id}" class="badge me-2 admidio-header-item {$headerItemClass}"{if $headerItem.tooltip != ''} title="{$headerItem.tooltip|escape}"{/if}>
            {if $headerItem.image != ''}<img class="admidio-header-item-image" src="{$headerItem.image|escape}" alt="">{elseif $headerItem.icon != ''}<i class="{$headerItem.icon}"></i>{/if} {$headerItem.label|escape}
        </span>
    {/if}
{/foreach}{/if}
