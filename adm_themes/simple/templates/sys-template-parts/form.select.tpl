
<div id="{$id}_group" class="form-control-group{if $data.formtype eq "navbar"} form-floating{else} mb-4{/if}{if $property eq 1} admidio-form-group-required{/if}">
    {if $data.formtype neq "navbar"}
        <label for="{$id}" class="form-label">
            {include file='sys-template-parts/parts/form.part.icon.tpl'}
            {$label}
        </label>
    {/if}
    <select id="{$id}" class="form-select {$class}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}>
        {assign "group" ""}
        {foreach $values as $optionvar}
            {if {array_key_exists key="group" array=$optionvar} && $optionvar["group"] neq $group}
                {if $group neq ""}</optgroup>{/if}
                <optgroup label="{$optionvar["group"]}">
                {assign "group" "{$optionvar["group"]}"}
            {/if}
            <option value="{$optionvar["id"]}" {if $defaultValue eq $optionvar["id"]}selected="selected"{/if}>{$optionvar["value"]}</option>
        {/foreach}
        {if $group neq ""}</optgroup>{/if}
    </select>
    {if $data.formtype eq "navbar"}
        <label for="{$id}" class="form-label">
            {include file='sys-template-parts/parts/form.part.icon.tpl'}
            {$label}
        </label>
    {/if}
    {if $data.formtype eq "navbar"}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    {else}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {/if}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
