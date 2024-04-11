<div id="{$id}_group" class="form-control-group{if $data.formtype eq "navbar"} form-floating{else} mb-4{/if}{if $property eq 1} admidio-form-group-required{/if}">
    {if $data.formtype neq "navbar"}
        <label for="{$id}" class="form-label">
            {include file='sys-template-parts/parts/form.part.icon.tpl'}
            {$label}
        </label>
    {/if}
    {if $maxUploadSize}
        <input type="hidden" id="MAX_FILE_SIZE" name="MAX_FILE_SIZE" value="{$maxUploadSize}" />
    {/if}
    {if $hideUploadField != true OR !$enableMultiUploads}
        <input type="file" name="userfile[]" class="form-control mb-2 {$class}"
            {foreach $data.attributes as $itemvar}
                {$itemvar@key}="{$itemvar}"
            {/foreach}
        >
    {/if}
    {if $data.formtype eq "navbar"}
        <label for="{$id}" class="form-label">
            {include file='sys-template-parts/parts/form.part.icon.tpl'}
            {$label}
        </label>
    {/if}
    {if $enableMultiUploads}
        <div>
            <button type="button" id="btn_add_attachment_{$id}" class="btn btn-primary">
                {include file='sys-template-parts/parts/form.part.icon.tpl'} {$multiUploadLabel}
            </button>
        </div>
    {/if}
    {if $data.formtype eq "navbar"}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    {else}
        {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {/if}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
