
<div id="{$id}_group" class="mb-4 form-check {if $property eq 1}admidio-form-group-required{/if}">
    <input class="form-check-input" type="checkbox" name="{$id}" id="{$id}" value="1"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
    <label class="form-check-label" for="{$id}">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
    </label>
    {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
