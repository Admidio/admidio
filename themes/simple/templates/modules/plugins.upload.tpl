{*
    Installing a plugin from a ZIP file the administrator already has.
*}
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    <div class="card admidio-field-group">
        <div class="card-header"><i class="bi bi-upload me-1"></i>{$l10n->get('SYS_PLUGIN_INSTALL_FROM_FILE')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.file.tpl' data=$elements['userfile']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['plugin_replace']}
        </div>
    </div>

    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_upload_plugin']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>

{$javascript}
