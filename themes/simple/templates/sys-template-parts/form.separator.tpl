<div class="admidio-form-group admidio-form-separator
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType neq "navbar"} mb-3{/if}">

    {if $data.separator_line|default:true}
        <hr id="{$data.id}"{if $data.class neq ""} class="{$data.class}"{else} class="form-separator-line"{/if}>
    {/if}
    {if $data.label neq ""}
        <label for="{$data.id}" class="admidio-form-separator-label {if $formType neq "vertical" and $formType neq "navbar"}col-form-label{else}form-label{/if}">
            {if !empty($data.collapse)}
            <a id="{$data.id}_caret" class=" admidio-open-close-caret" data-target="{$data.collapse}">
            <i class="bi bi-caret-{if !empty($data.collapsed)}right{else}down{/if}-fill" style="margin-right: 0"></i>
            </a>
            {/if}
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>
    {/if}
</div>
