<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    
    {include 'sys-template-parts/form.description.tpl' data=$elements['adm_inventory_import_description']}
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['format']}
    {include 'sys-template-parts/form.file.tpl' data=$elements['userfile']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['import_sheet']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_encoding']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_separator']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['import_enclosure']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_forward']}
</form>