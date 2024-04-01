<div class="form-control-group form-custom-content{if $data.formtype neq "navbar"} mb-4{/if}">
    <label for="{$id}" class="form-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
    </label>
    <div id="{$id}">{$content}</div>
    {if $data.formtype eq "navbar"}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    {else}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {/if}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
