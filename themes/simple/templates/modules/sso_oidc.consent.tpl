<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.description.tpl' data=$elements['oidc_consent_description']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['oidc_consent_scopes']}

    <div class="form-alert" style="display: none;">&nbsp;</div>

    <div class="d-flex gap-2">
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_approve']}
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_deny']}
    </div>
</form>