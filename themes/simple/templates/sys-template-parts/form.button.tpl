<button id="{$data.id}" name="{$data.id}" class="btn focus-ring {$data.class}"
    {if $data.link}onclick="self.location.href='{$data.link}'" {/if}
    {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}"
    {/foreach}
>
    {include file="sys-template-parts/parts/form.part.icon.tpl"}{$data.value}
</button>



