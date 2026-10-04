{*
    The settings panel of a plugin that declared its settings in its manifest but no panel of its
    own. The controls are generated from that declaration, so unlike every other preferences
    template this one cannot name its elements and routes each of them by its type instead.
*}
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {foreach $elements as $element}
        {if $element.type eq 'checkbox'}
            {include 'sys-template-parts/form.checkbox.tpl' data=$element}
        {elseif $element.type eq 'select'}
            {include 'sys-template-parts/form.select.tpl' data=$element}
        {elseif $element.type eq 'submit'}
            {include 'sys-template-parts/form.button.tpl' data=$element}
        {else}
            {include 'sys-template-parts/form.input.tpl' data=$element}
        {/if}
    {/foreach}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
