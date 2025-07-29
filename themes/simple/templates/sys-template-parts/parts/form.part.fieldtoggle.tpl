{* add a toggle checkbox if editing a selection *}
{if $data.toggleable}
    <div class="admidio-form-group form-check form-switch col-sm-3">
        <label for="{$data.id}" class="{if $formType neq "vertical" and $formType neq "navbar"}col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
            <input class="form-check-input focus-ring" type="checkbox" id="toggle_{$data.id}" name="toggle_{$data.id}" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FORM_USE_FOR_EDIT')}" value="1"tabindex="-1" autofocus="false">
        </label>
    </div>
{else}
    <label for="{$data.id}" class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$data.label}
    </label>
{/if}