<div id="{$data.id}_group" class="form-group-editor admidio-form-group mb-4
{if $data.property eq 1} admidio-form-group-required{/if}">
    <label for="{$data.id}" class="form-label">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$data.label}
    </label>
    <div class="editor {$data.class}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
        <textarea id="{$data.id}" name="{$data.id}" style="width: 100%">{$data.value}</textarea>
    </div>

    {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
</div>
