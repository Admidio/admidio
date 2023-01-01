{if $data.property eq 4}
<textarea
         style="display: none;"   
name="{$id}"
id="{$id}"
value="{$value}"
{foreach $data.attributes as $itemvar}
{$itemvar@key}="{$itemvar}"
{/foreach}
></textarea>
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
        <textarea
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >{$value}</textarea>
            {if $maxLength > 0}
            <small class="characters-count">(noch <span id="{$id}_counter" class="">255</span> Zeichen)</small>
            {/if}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
        {include file='sys-template-parts/parts/form.part.warning.tpl'}
    </div>
</div>
{/if}