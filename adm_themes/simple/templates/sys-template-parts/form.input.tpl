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
    class="form-group {if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}row{/if} {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="{if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}col-sm-3{/if} control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
    <div class="{if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}col-sm-9{/if}">
        {if $type == 'datetime'}
            <input
            type="date"
            name="{$id}"
            id="{$id}"
            value="{$data.attributes.dateValue}"
            {foreach $data.attributes.dateValueAttributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            <input
            type="time"
            name="{$id}_time"
            id="{$id}_time"
            value="{$data.attributes.timeValue}"
            {foreach $data.attributes.timeValueAttributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {elseif $type == 'date'}
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
            {if $data.passwordStrength eq 1}
            <div id="admidio-password-strength" class="progress {$data.attributes.class}">
                <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
                <div id="admidio-password-strength-minimum"></div>
            </div>
            {/if}
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
