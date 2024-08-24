<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['documents_files_module_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['documents_files_max_upload_size']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save_documents_files']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
