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
            {include file="sys-template-parts/parts/form.part.fieldtoggle.tpl"}
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
