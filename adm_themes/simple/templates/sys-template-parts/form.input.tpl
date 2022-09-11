{if $data.property eq 4}
<input
    type="{$type}"
    name="{$id}"
    id="{$id}"
    value="{$value}"
    {foreach $data.attributes as $itemvar}
    {$itemvar@key}="{$itemvar}"
    {/foreach}>
{else}
<div
    id="{$id}_group"
    class="form-group row {if $property eq 1}admidio-form-group-required{/if}">    
    <label for="{$id}" class="col-sm-3 control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
    <div class="col-sm-9">
        {if $type == 'datetime'}
            <input
            type="datetime-local"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {elseif $type == 'date' OR $type == 'birthday'}{$value}
            <input
            type="date"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {elseif $type == 'password'}
            <input
            type="{$type}"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            
            <div id="admidio-password-strength" class="progress ' . $optionsAll['class'] . '">
                <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
                <div id="admidio-password-strength-minimum"></div>
            </div>

            {$htmlAfter}
        {else}
        <input
            type="{$type}"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {/if}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
{/if}