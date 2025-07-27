<div class="admidio-form-group admidio-form-seperator
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType neq "navbar"} mb-3{/if}">

    <hr id="{$data.id}"{if $data.class neq ""} class="{$data.class}"{else} class="form-separator-line"{/if}>
    {if $data.label neq ""}
        <label for="{$data.id}" class="{if $formType neq "vertical" and $formType neq "navbar"}row col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            <u>{$data.label}</u>
        </label>
    {/if}
</div>
