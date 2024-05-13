<button id="{$id}" name="{$id}" class="btn focus-ring {$class}"
    {if $link}onclick="self.location.href='{$link}'" {/if}
    {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}"
    {/foreach}
>
    {include file="sys-template-parts/parts/form.part.icon.tpl"}{$value}
</button>



