{* Render one action for either the visible icons or the overflow menu. *}
<a class="{$actionClass}{if isset($actionItem.class)} {$actionItem.class}{/if}{if isset($actionItem.popup)} openPopup{elseif isset($actionItem.dataMessage)} admidio-messagebox{/if}"
    {if isset($actionItem.popup)} href="javascript:void(0);" data-class="{$actionItem.popupClass|default:'modal-lg'}" data-href="{$actionItem.dataHref}"
    {elseif isset($actionItem.dataMessage)} href="javascript:void(0);" {if isset($actionItem.dataHref) || isset($actionItem.buttons)}data-buttons="{$actionItem.buttons|default:'yes-no'}"{/if} data-message="{$actionItem.dataMessage|escape:'html'}"{if isset($actionItem.dataHref)} data-href="{$actionItem.dataHref}"{/if}
    {else} href="{$actionItem.url|default:'javascript:void(0);'}"{/if}
    {if isset($actionItem.attributes)}{foreach $actionItem.attributes as $attribute => $value} {$attribute}="{$value|escape:'html'}"{/foreach}{/if}
    aria-label="{$actionItem.tooltip|escape:'html'}">
    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip|escape:'html'}"></i>{if $actionClass === 'dropdown-item'} {$actionItem.tooltip}{else}<span class="visually-hidden">{$actionItem.tooltip|escape:'html'}</span>{/if}</a>
