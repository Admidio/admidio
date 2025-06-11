<script type="text/javascript">$(function() {
        $("#adm_plugin_login_form").submit(formSubmit);
    });
</script>

<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('SYS_LOGIN')}</h3>

    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>

        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['plg_usr_login_name']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['plg_usr_password']}
        {if $settings->getBool('two_factor_authentication_enabled')}
            {include 'sys-template-parts/form.input.tpl' data=$elements['usr_totp_code']}
        {/if}
        {if $currentOrganization->getValue('org_show_org_select')}
            {include 'sys-template-parts/form.select.tpl' data=$elements['plg_org_shortname']}
        {/if}
        {if $settings->getBool('enable_auto_login')}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['plg_auto_login']}
        {/if}
        {include 'sys-template-parts/form.button.tpl' data=$elements['plg_btn_login']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
    </form>

    {if $showRegisterLink && $settings->getBool('registration_module_enabled')}
        <a class="icon-link" href="{$urlAdmidio}/modules/registration.php"><i class="bi bi-card-checklist"></i>{$l10n->get('SYS_REGISTRATION')}</a>
    {/if}
</div>
