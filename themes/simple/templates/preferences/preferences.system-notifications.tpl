<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_notifications_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_notifications_new_entries']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_notifications_profile_changes']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['system_notifications_role']}
    <div class="admidio-form-group admidio-form-custom-content row mb-3">
        <label for="systemNotifications" class="col-sm-3 col-form-label">
            {$l10n->get('SYS_SYSTEM_MAILS')}
        </label>
        <div class="col-sm-9">
            <div id="systemNotifications">
                <p>{$l10n->get('SYS_SYSTEM_MAIL_TEXTS_DESC')}:</p>
                <p>
                    <strong>#user_first_name#</strong> - {$l10n->get('ORG_VARIABLE_FIRST_NAME')}<br />
                    <strong>#user_last_name#</strong> - {$l10n->get('ORG_VARIABLE_LAST_NAME')}<br />
                    <strong>#user_login_name#</strong> - {$l10n->get('ORG_VARIABLE_USERNAME')}<br />
                    <strong>#user_email#</strong> - {$l10n->get('ORG_VARIABLE_EMAIL')}<br />
                    <strong>#administrator_email#</strong> - {$l10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION')}<br />
                    <strong>#organization_short_name#</strong> - {$l10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION')}<br />
                    <strong>#organization_long_name#</strong> - {$l10n->get('ORG_VARIABLE_NAME_ORGANIZATION')}<br />
                    <strong>#organization_homepage#</strong> - {$l10n->get('ORG_VARIABLE_URL_ORGANIZATION')}
                </p>
            </div>
        </div>
    </div>
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['SYSMAIL_REGISTRATION_CONFIRMATION']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['SYSMAIL_REGISTRATION_NEW']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['SYSMAIL_REGISTRATION_APPROVED']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['SYSMAIL_REGISTRATION_REFUSED']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['SYSMAIL_LOGIN_DATA']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['SYSMAIL_PASSWORD_RESET']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_system_notification']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
