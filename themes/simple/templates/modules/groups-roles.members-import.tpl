<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['format']}
    {include 'sys-template-parts/form.file.tpl' data=$elements['userfile']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_coding']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_separator']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['identify_method']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['first_row_header']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_preview']}
</form>
