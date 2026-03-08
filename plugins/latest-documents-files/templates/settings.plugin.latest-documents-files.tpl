<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['latest_documents_files_plugin_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['latest_documents_files_files_count']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['latest_documents_files_show_upload_timestamp']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['latest_documents_files_max_chars_filename']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_latest_documents_files']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}