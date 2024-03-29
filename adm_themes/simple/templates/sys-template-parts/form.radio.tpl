{if $data.property eq 4}
    <input type="{$type}" name="{$id}" id="{$id}" value="{$value}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
{else}
    <div id="{$id}_group" class="mb-4 {if $property eq 1}admidio-form-group-required{/if}">
        <label for="{$id}" class="form-label">
            {include file='sys-template-parts/parts/form.part.icon.tpl'}
            {$label}
        </label>
        <div>
            {if $showNoValueButton}
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="{$id}" id="{$id}_0" class="{$data.attributes.class}" value="0">
                    <label for="{$id}_0" class="form-check-label">---</label>
                </div>
            {/if}
            {foreach $values as $optionvar}
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="{$id}" id="{$id}_{$optionvar@key}" value="{$optionvar@key}"
                        {foreach $data.attributes as $itemvar}
                            {$itemvar@key}="{$itemvar}"
                        {/foreach}
                        {if $defaultValue eq $optionvar@key}checked="checked"{/if}
                    >
                    <label for="{$id}_{$optionvar@key}" class="form-check-label">{$optionvar}</label>
                </div>
            {/foreach}

            {include file='sys-template-parts/parts/form.part.helptext.tpl'}
            {include file='sys-template-parts/parts/form.part.warning.tpl'}
        </div>
    </div>
{/if}
