<div style="max-width: 500px">
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>

        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['usr_login_name']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['usr_password']}
        {if $settings->getBool('enable_two_factor_authentication')}
            {include 'sys-template-parts/form.input.tpl' data=$elements['usr_totp_code']}
        {/if}
        {if $currentOrganization->getValue('org_show_org_select')}
            {include 'sys-template-parts/form.select.tpl' data=$elements['org_shortname']}
        {/if}
        {if $settings->getBool('enable_auto_login')}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['auto_login']}
        {/if}
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_login']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
    </form>

    {if $settings->getBool('registration_enable_module')}
        <div class="card admidio-field-group mt-5">
            <div class="card-body">
                <p>{$l10n->get('SYS_NO_LOGIN_DATA')}</p>
                <a class="btn btn-secondary" href="{$urlAdmidio}/adm_program/modules/registration.php">{$l10n->get("SYS_REGISTER")}</a>
            </div>
        </div>
    {/if}
</div>
