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
            <input type="radio" name="{$id}" id="{$id}_0" class="{$data.attributes.class}">---</label>
            {/if}
            {foreach $values as $itemvar}
            <label for="{$id}_{$itemvar@key}" class="radio-inline">
                <input type="radio" name="{$id}" id="{$id}_{$itemvar@key}" value="{$itemvar@key}"
                {foreach $data.attributes as $itemvar}
                {$itemvar@key}="{$itemvar}"
                {/foreach} {if $defaultValue eq $itemvar@key}checked="checked"{/if} >{$itemvar}</label>
            {/foreach}

            
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
{/if}