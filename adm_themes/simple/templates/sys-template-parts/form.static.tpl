
<div id="{$id}_group" class="form-control-group{if $data.formtype neq "navbar"} mb-4{/if}{if $property eq 1} admidio-form-group-required{/if}">
    <label for="{$id}" class="form-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
    </label>
    <div>
        <p {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}" {/foreach}>{$value}</p>

        {if $data.formtype eq "navbar"}
            {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
        {else}
            {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {/if}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
