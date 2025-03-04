{if $data.property eq 4}
{else}
<div class="admidio-form-group admidio-form-custom-content
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType neq "navbar"} mb-3{/if}">
    <label for="{$data.id}" class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$data.label}
    </label>
    {if $formType neq "vertical" and $formType neq "navbar"}<div class="col-sm-9">{/if}
    <div id="{$data.id}"{if $data.class neq ""} class="{$data.class}"{/if}>{$data.content}</div>
    {if $formType eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
    {if $formType neq "vertical" and $formType neq "navbar"}</div>{/if}
</div>
{/if}