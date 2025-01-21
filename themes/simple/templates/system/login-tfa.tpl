<div style="max-width: 500px">
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>
        <div class="card-body">
            <p>{$l10n->get('SYS_TFA_PROTECTED_CONTENT')}</p>
            <p>{$l10n->get('SYS_TFA_ENTER')}</p>
        </div>
        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['usr_totp_code']}
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_tfa']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
    </form>
</div>
