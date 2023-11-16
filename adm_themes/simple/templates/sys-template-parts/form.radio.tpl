{if $data.property eq 4}
<input type="{$type}" name="{$id}" id="{$id}" value="{$value}" {foreach $data.attributes as $itemvar}
    {$itemvar@key}="{$itemvar}" {/foreach}>
{else}
<div id="{$id}_group" class="form-group row {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="col-sm-3 control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
    <div class="col-sm-9">
        {if $showNoValueButton}
            <label for="{$id}_0" class="radio-inline">
            <input type="radio" name="{$id}" id="{$id}_0" class="{$data.attributes.class}" value="0">---</label>
            {/if}
            {foreach $values as $optionvar}
            <label for="{$id}_{$optionvar@key}" class="radio-inline">
                <input type="radio" name="{$id}" id="{$id}_{$optionvar@key}" value="{$optionvar@key}"
                {foreach $data.attributes as $itemvar}
                    {$itemvar@key}="{$itemvar}"
                {/foreach} {if $defaultValue eq $optionvar@key}checked="checked"{/if} >{$optionvar}</label>
            {/foreach}

        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
{/if}
