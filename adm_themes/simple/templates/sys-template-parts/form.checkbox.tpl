{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}
    <div class="row mb-3">
        <div class="col-sm-9 offset-sm-3">
{/if}
<div id="{$data.id}_group" class="admidio-form-group form-check form-switch
    {if $data.formtype neq "navbar"} mb-4{/if}
    {if $data.property eq 1} admidio-form-group-required{/if}">
    <input id="{$data.id}" name="{$data.id}" class="form-check-input focus-ring {$data.class}" type="checkbox" value="1"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
    <label class="form-check-label" for="{$data.id}">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$data.label}
    </label>
    {if $data.formtype eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
</div>
{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}</div></div>{/if}
