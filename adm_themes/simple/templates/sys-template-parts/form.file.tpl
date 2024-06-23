<div id="{$data.id}_group" class="admidio-form-group
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType eq "navbar"} form-floating{else} mb-4{/if}
    {if $data.property eq 1} admidio-form-group-required{/if}">

    {if $formType neq "navbar"}
        <label for="{$data.id}" class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>
    {/if}
    {if $data.maxUploadSize}
        <input type="hidden" id="MAX_FILE_SIZE" name="MAX_FILE_SIZE" value="{$data.maxUploadSize}" />
    {/if}

    {if $formType neq "vertical" and $formType neq "navbar"}<div class="col-sm-9">{/if}

    {if $data.hideUploadField != true OR !$data.enableMultiUploads}
        <input type="file" name="userfile[]" class="form-control mb-2 focus-ring {$data.class}"
            {foreach $data.attributes as $itemvar}
                {$itemvar@key}="{$itemvar}"
            {/foreach}
        >
    {/if}
    {if $formType eq "navbar"}
        <label for="{$data.id}" class="form-label">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>
    {/if}
    {if $data.enableMultiUploads}
        <div>
            <button type="button" id="btn_add_attachment_{$data.id}" class="btn btn-primary focus-ring">
                {include file="sys-template-parts/parts/form.part.icon.tpl"} {$data.multiUploadLabel}
            </button>
        </div>
    {/if}
    {if $formType eq "navbar"}
        {include file="sys-template-parts/parts/form.part.iconhelp.tpl"}
    {else}
        {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {/if}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
    {if $formType neq "vertical" and $formType neq "navbar"}</div>{/if}
</div>
