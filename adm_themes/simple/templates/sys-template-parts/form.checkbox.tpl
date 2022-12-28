<div id="{$id}_group" class="form-group {if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}row{/if} {if $property eq 1}admidio-form-group-required{/if}">
    <div class="{if $data.formtype neq 'vertical' and $data.formtype neq 'navbar'}offset-sm-3 col-sm-9{/if}">
        <div class="checkbox">
            <label>
                <input type="checkbox" name="{$id}" id="{$id}" value="1" {foreach $data.attributes as $itemvar}
                {$itemvar@key}="{$itemvar}"
                {/foreach} >
                    {include file='sys-template-parts/parts/form.part.icon.tpl'}
                    {$label}
                    {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
            </label>
            {include file='sys-template-parts/parts/form.part.helptext.tpl'}
            {include file='sys-template-parts/parts/form.part.warning.tpl'}
        </div>
    </div>
</div>