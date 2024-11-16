<p class="lead">{$l10n->get('SYS_PASSWORD_FORGOTTEN_DESCRIPTION')}</p>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['recipient_email']}
    {if {array_key_exists array=$elements key='adm_captcha_code'}}
        {include 'sys-template-parts/form.captcha.tpl' data=$elements['adm_captcha_code']}
    {/if}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_send']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
