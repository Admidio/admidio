{if $data.property eq 4}
    <input type="{$data.type}" name="{$data.id}" id="{$data.id}" value="{$data.value}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
{else}
    <div id="{$data.id}_group" class="admidio-form-group
        {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
        {if $formType neq "navbar"} mb-3{/if}{if $data.property eq 1} admidio-form-group-required{/if}">
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
        <div{if $formType neq "vertical" and $formType neq "navbar"} class="col-sm-9"{/if}>
            {if $data.showNoValueButton}
                <div class="form-check form-check-inline">
                    <input id="{$data.id}_0" name="{$data.id}" class="form-check-input {$data.class}" type="radio" value="0">
                    <label for="{$data.id}_0" class="form-check-label">---</label>
                </div>
            {/if}
            {foreach $data.values as $optionvar}
                <div class="form-check form-check-inline">
                    <input id="{$data.id}_{$optionvar@key}" name="{$data.id}" class="form-check-input focus-ring {$data.class}" type="radio" value="{$optionvar@key}"
                        {foreach $data.attributes as $itemvar}
                            {$itemvar@key}="{$itemvar}"
                        {/foreach}
                        {if $data.defaultValue eq $optionvar@key}checked="checked"{/if}
                    >
                    <label for="{$data.id}_{$optionvar@key}" class="form-check-label">{$optionvar}</label>
                </div>
            {/foreach}

            {if $formType eq "navbar"}
                {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
            {else}
                {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {/if}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}
        </div>
    </div>
{/if}
