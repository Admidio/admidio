
<div id="{$id}_group" class="mb-4 {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="form-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
    </label>
    <div>
        <p {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}" {/foreach}>{$value}</p>

        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
