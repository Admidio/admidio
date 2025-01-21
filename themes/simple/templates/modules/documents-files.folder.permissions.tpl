<p class="lead">{$l10n->get('SYS_ROLE_ACCESS_PERMISSIONS_DESC', [$folderName])}</p>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['adm_roles_view_right']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['adm_roles_upload_right']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_administrators']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
