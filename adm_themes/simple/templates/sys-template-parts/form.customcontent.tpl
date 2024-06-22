<div class="admidio-form-group admidio-form-custom-content
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}row{/if}
    {if $data.formtype neq "navbar"} mb-4{/if}">
    <label for="{$data.id}" class="{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$label}
    </label>
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}<div class="col-sm-9">{/if}
    <div id="{$data.id}"{if $data.class neq ""} class="{$data.class}"{/if}>{$data.content}</div>
    {if $data.formtype eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}</div>{/if}
</div>
