<p class="lead">{$l10n->get('SYS_NEW_ORGANIZATION_DESC')}</p>
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_organization_short_name']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_organization_long_name']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_organization_email']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_forward']}
</form>
