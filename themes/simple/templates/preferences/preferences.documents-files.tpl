<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['documents_files_module_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['documents_files_max_upload_size']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_documents_files']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
