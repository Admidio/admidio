<style>
    #adm_sidebar,
    .admidio-breadcrumb,
    .admidio-content-header {
        display: none;
    }

    .admidio-content-col {
        flex: 0 0 100%;
        width: 100%;
        max-width: 100%;
    }

    #adm_content {
        max-width: none;
        padding: 2rem;
    }

    .sso-login-dialog {
        width: min(700px, calc(100vw - 4rem));
        margin-right: auto;
        margin-left: auto;
    }

    .sso-login-form {
        max-width: 800px;
        margin-right: auto;
        margin-left: auto;
    }

    .sso-login-actions .offset-sm-3 {
        margin-left: 0;
    }

    @media (max-width: 576px) {
        #adm_content {
            padding: 1rem;
        }

        .sso-login-dialog {
            width: 100%;
        }
    }
</style>

<div class="sso-login-dialog">
    <div class="card shadow">
        <div class="card-header">
            <h1 class="h4 mb-0 text-center">
                {$ssoLoginHeadline}
            </h1>
        </div>

        <div class="card-body">
            {if $ssoLoginMessage !== ''}
                {$ssoLoginMessage nofilter}
            {/if}

            <div class="sso-login-form">
                <form {foreach $attributes as $attribute}
                        {$attribute@key}="{$attribute}"
                    {/foreach}>

                    {include 'sys-template-parts/form.input.tpl'
                        data=$elements['adm_csrf_token']}

                    {include 'sys-template-parts/form.input.tpl'
                        data=$elements['usr_login_name']}

                    {include 'sys-template-parts/form.input.tpl'
                        data=$elements['usr_password']}

                    {if $settings->getBool('two_factor_authentication_enabled')}
                        {include 'sys-template-parts/form.input.tpl'
                            data=$elements['usr_totp_code']}
                    {/if}

                    {if $currentOrganization->getValue('org_show_org_select')}
                        {include 'sys-template-parts/form.select.tpl'
                            data=$elements['org_shortname']}
                    {/if}

                    {if $settings->getBool('enable_auto_login')}
                        {include 'sys-template-parts/form.checkbox.tpl'
                            data=$elements['auto_login']}
                    {/if}

                    <div class="sso-login-actions d-flex justify-content-center">
                        {include 'sys-template-parts/form.button.tpl'
                            data=$elements['adm_button_login']}
                    </div>

                    <div class="form-alert mt-3" style="display: none;">
                        &nbsp;
                    </div>
                </form>

                {if $settings->getBool('registration_module_enabled')}
                    <div class="card admidio-field-group mt-4" id="registration_card">
                        <div class="card-body text-center">
                            <p>{$l10n->get('SYS_NO_LOGIN_DATA')}</p>

                            <a class="btn btn-secondary"
                               href="{$urlAdmidio}/modules/registration.php">
                                {$l10n->get('SYS_REGISTER')}
                            </a>
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>