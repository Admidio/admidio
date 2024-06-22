<div id="{$data.id}_group" class="admidio-form-group
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}row{/if}
    {if $data.formtype eq "navbar"} form-floating{else} mb-4{/if}
    {if $data.property eq 1} admidio-form-group-required{/if}">
    {if $data.formtype neq "navbar"}
        <label for="{$data.id}" class="{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>
    {/if}
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}<div class="col-sm-9">{/if}
    <select id="{$data.id}" class="form-select focus-ring {$data.class}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}>
        {assign "group" ""}
        {foreach $data.values as $optionvar}
            {if {array_key_exists key="group" array=$optionvar} && $optionvar["group"] neq $group}
                {if $group neq ""}</optgroup>{/if}
                <optgroup label="{$optionvar["group"]}">
                {assign "group" "{$optionvar["group"]}"}
            {/if}
            <option value="{$optionvar["id"]}" {if $data.defaultValue eq $optionvar["id"]}selected="selected"{/if}>{$optionvar["value"]}</option>
        {/foreach}
        {if $group neq ""}</optgroup>{/if}
    </select>
    {if $data.formtype eq "navbar"}
        <label for="{$data.id}" class="form-label">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>
    {/if}
    {if $data.formtype eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}</div>{/if}
</div>
