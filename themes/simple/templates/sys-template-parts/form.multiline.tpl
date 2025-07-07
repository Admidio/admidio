{if $data.property eq 4}
    <textarea style="display: none;" name="{$data.id}" id="{$data.id}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >{$value}</textarea>
{else}
    <div id="{$data.id}_group" class="admidio-form-group
        {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
        {if $formType neq "navbar"} mb-3{/if}
        {if $data.property eq 1} admidio-form-group-required{/if}">
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
            <textarea id="{$data.id}" name="{$data.id}" class="form-control focus-ring {$data.class}"
                {foreach $data.attributes as $itemvar}
                    {$itemvar@key}="{$itemvar}"
                {/foreach}
                >{$data.value}</textarea>
            {if $data.maxLength > 0}
                <small class="characters-count">({$l10n->get("SYS_STILL_X_CHARACTERS", array('<span id="'|cat:$data.id|cat:'_counter" class="">255</span>'))})</small>
            {/if}
            {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}
        </div>
    </div>
{/if}
