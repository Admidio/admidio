
<div id="{$id}_group" class="form-control-group form-check form-switch{if $data.formtype neq "navbar"} mb-4{/if}{if $property eq 1} admidio-form-group-required{/if}">
    <input id="{$id}" name="{$id}" class="form-check-input focus-ring {$class}" type="checkbox" value="1"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
    <label class="form-check-label" for="{$id}">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
    </label>
    {if $data.formtype eq "navbar"}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    {else}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {/if}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
