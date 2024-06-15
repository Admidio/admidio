<div id="{$id}_group" class="admidio-form-group
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}row{/if}
    {if $data.formtype eq "navbar"} form-floating{else} mb-4{/if}
    {if $property eq 1} admidio-form-group-required{/if}">

    {if $data.formtype neq "navbar"}
        <label for="{$id}" class="{if $data.formtype neq "vertical" and $data.formtype neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$label}
        </label>
    {/if}
    {if $maxUploadSize}
        <input type="hidden" id="MAX_FILE_SIZE" name="MAX_FILE_SIZE" value="{$maxUploadSize}" />
    {/if}

    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}<div class="col-sm-9">{/if}

    {if $hideUploadField != true OR !$enableMultiUploads}
        <input type="file" name="userfile[]" class="form-control mb-2 focus-ring {$class}"
            {foreach $data.attributes as $itemvar}
                {$itemvar@key}="{$itemvar}"
            {/foreach}
        >
    {/if}
    {if $data.formtype eq "navbar"}
        <label for="{$id}" class="form-label">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$label}
        </label>
    {/if}
    {if $enableMultiUploads}
        <div>
            <button type="button" id="btn_add_attachment_{$id}" class="btn btn-primary focus-ring">
                {include file="sys-template-parts/parts/form.part.icon.tpl"} {$multiUploadLabel}
            </button>
        </div>
    {/if}
    {if $data.formtype eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
    {if $data.formtype neq "vertical" and $data.formtype neq "navbar"}</div>{/if}
</div>
