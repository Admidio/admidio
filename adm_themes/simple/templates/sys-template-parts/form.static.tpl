
<div id="{$id}_group" class="form-group {if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}row{/if} {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="{if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}col-sm-3{/if} control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
    <div class="{if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}col-sm-9{/if}">
        <p {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}" {/foreach}>{$value}</p>

        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
