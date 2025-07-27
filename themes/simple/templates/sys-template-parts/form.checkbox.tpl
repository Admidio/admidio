{if $formType neq "vertical" and $formType neq "navbar"}
    {* add a toggle checkbox if editing a selection *}
    {if $data.toggleable}
        <div class="admidio-form-group row mb-3">
            <div class="admidio-form-group form-check form-switch col-sm-3">
                <input class="form-check-input focus-ring" type="checkbox" id="toggle_{$data.id}" name="toggle_{$data.id}" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FORM_USE_FOR_EDIT')}" value="1" tabindex="-1" autofocus="false">
            </div>
            <div class="col-sm-9">
    {else}
        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
    {/if}
{/if}
<div id="{$data.id}_group" class="admidio-form-group form-check form-switch
    {if $formType eq "vertical"} mb-3{/if}
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
    {if $formType eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
</div>
{if $formType neq "vertical" and $formType neq "navbar"}</div></div>{/if}
