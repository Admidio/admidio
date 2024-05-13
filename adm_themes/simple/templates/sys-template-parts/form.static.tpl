
<div id="{$id}_group" class="admidio-form-group
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}row{/if}
    {if $data.formtype neq "navbar"} mb-4{/if}
    {if $property eq 1} admidio-form-group-required{/if}">
    <label for="{$id}" class="{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$label}
    </label>
    <div {if $data.formtype neq "vertical" and $data.formtype neq "navbar"} class="col-sm-9"{/if}>
        <p id="{$id}" class="form-control-static {$class}">{$value}</p>

        {if $data.formtype eq "navbar"}
            {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
        {else}
            {include file="sys-template-parts/parts/form.part.helptext.tpl"}
        {/if}
        {include file="sys-template-parts/parts/form.part.warning.tpl"}
    </div>
</div>
