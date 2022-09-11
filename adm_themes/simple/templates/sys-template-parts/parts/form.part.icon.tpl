{if $icon}
    {if Image::isFontAwesomeIcon($icon)}
        <i class="{$icon} fas" {if $label}data-toggle="tooltip" title="{$label}"{/if}></i>
    {else}
        <img src="{$icon}" alt="{$label}" />
    {/if}
{/if}