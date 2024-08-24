<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['format']}
    {include 'sys-template-parts/form.file.tpl' data=$elements['userfile']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['import_sheet']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_separator']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_enclosure']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_role_uuid']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['user_import_mode']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_forward']}
</form>
