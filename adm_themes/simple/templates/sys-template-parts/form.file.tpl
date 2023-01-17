<div
    id="{$id}_group"
    class="form-group form-upload row {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="col-sm-3 control-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
        {include file='sys-template-parts/parts/form.part.iconhelp.tpl'}
    </label>
  <div class="col-sm-9">
    {if $maxUploadSize}
    <input
      type="hidden"
      name="MAX_FILE_SIZE"
      id="MAX_FILE_SIZE"
      value="{$maxUploadSize}"
    />
    {/if}
    {if $hideUploadField != true OR !$enableMultiUploads}
        <input type="file" name="userfile[]"
            {foreach $data.attributes as $itemvar}
                {$itemvar@key}="{$itemvar}"
            {/foreach}
        />
    {/if}

    {if $enableMultiUploads}
    <button
      type="button"
      id="btn_add_attachment_{$id}"
      class="btn btn-secondary"
    >
        {include file='sys-template-parts/parts/form.part.icon.tpl'} {$multiUploadLabel}
    </button>
    {/if}
    {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
  </div>
</div>
