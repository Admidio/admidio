<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_KEY_SETTING')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl'  data=$elements['key_name']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['key_algorithm']}
            {include 'sys-template-parts/form.multiline.tpl' data=$elements['key_public']}
            {include 'sys-template-parts/form.custom-content.tpl' data=$elements['key_private']}
            {include 'sys-template-parts/form.multiline.tpl' data=$elements['key_certificate']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['key_is_active']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_CERTIFICATE_SETTINGS')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_common_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_org']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_orgunit']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_locality']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_state']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_country']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['cert_admin_email']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['key_expires_at']}

        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
