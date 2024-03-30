{if $icon}
    {if {is_font_awesome_icon icon=$icon}}
        <i class="{$icon} fas" {if isset($label)}data-bs-toggle="tooltip" title="{$label}"{/if}></i>
    {else}
        <img src="{$icon}" {if isset($label)}alt="{$label}"{/if} />
    {/if}
{/if}
