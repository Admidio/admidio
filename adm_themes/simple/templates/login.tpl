<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['usr_login_name']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['usr_password']}
    {if $settings->getBool('system_organization_select')}
        {include 'sys-template-parts/form.select.tpl' data=$elements['org_shortname']}
    {/if}
    {if $settings->getBool('enable_auto_login')}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['auto_login']}
    {/if}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_login']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>

{if $settings->getBool('enable_auto_login')}
    <div id="login_registration_link">
        <small>
            <a href="{$ADMIDIO_URL}/adm_program/modules/registration/registration.php">{$l10n->get("SYS_WANT_REGISTER")}</a>
        </small>
    </div>
{/if}
<div id="login_forgot_password_link" class="admidio-margin-bottom">
    <small><a href="{$forgotPasswordLink}">{$l10n->get("SYS_FORGOT_MY_PASSWORD")}</a></small>
</div>
