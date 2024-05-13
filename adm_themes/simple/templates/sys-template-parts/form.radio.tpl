{if $data.property eq 4}
    <input type="{$type}" name="{$id}" id="{$id}" value="{$value}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
{else}
    <div id="{$id}_group" class="admidio-form-group
        {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}row{/if}
        {if $data.formtype neq "navbar"} mb-4{/if}{if $property eq 1} admidio-form-group-required{/if}">
        <label for="{$id}" class="{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$label}
        </label>
        <div{if $data.formtype neq "vertical" and $data.formtype neq "navbar"} class="col-sm-9"{/if}>
            {if $showNoValueButton}
                <div class="form-check form-check-inline">
                    <input id="{$id}_0" name="{$id}" class="form-check-input {$class}" type="radio" value="0">
                    <label for="{$id}_0" class="form-check-label">---</label>
                </div>
            {/if}
            {foreach $values as $optionvar}
                <div class="form-check form-check-inline">
                    <input id="{$id}_{$optionvar@key}" name="{$id}" class="form-check-input focus-ring {$class}" type="radio" value="{$optionvar@key}"
                        {foreach $data.attributes as $itemvar}
                            {$itemvar@key}="{$itemvar}"
                        {/foreach}
                        {if $defaultValue eq $optionvar@key}checked="checked"{/if}
                    >
                    <label for="{$id}_{$optionvar@key}" class="form-check-label">{$optionvar}</label>
                </div>
            {/foreach}

            {if $data.formtype eq "navbar"}
                {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
            {else}
                {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {/if}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}
        </div>
    </div>
{/if}
