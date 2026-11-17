{* Ordering actions: direction (UP, DOWN or FREE), label and optional class,
   attributes and showText. Preserve caller-specific sorting hooks. *}
{foreach $moveActions as $moveAction}
<a class="{if empty($moveAction.showText)}admidio-icon-link {/if}{if !empty($moveAction.class)}{$moveAction.class|escape:'html'}{/if}" href="javascript:void(0);"
   {if $moveAction.direction !== 'FREE'}data-direction="{$moveAction.direction|escape:'html'}"{/if}
   {if isset($moveAction.attributes)}{foreach $moveAction.attributes as $attribute => $value} {$attribute}="{$value|escape:'html'}"{/foreach}{/if}
   aria-label="{$moveAction.label|escape:'html'}">
    <i class="bi {if $moveAction.direction === 'UP'}bi-arrow-up-circle-fill{elseif $moveAction.direction === 'DOWN'}bi-arrow-down-circle-fill{else}bi-arrows-move handle{/if}"{if empty($moveAction.showText)} data-bs-toggle="tooltip" title="{$moveAction.label|escape:'html'}"{/if}></i>{if !empty($moveAction.showText)}{$moveAction.label|escape:'html'}{else}<span class="visually-hidden">{$moveAction.label|escape:'html'}</span>{/if}</a>
    {if !empty($moveAction.showText) && !$moveAction@last}<br>{/if}
{/foreach}
