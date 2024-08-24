<p class="lead">{$l10n->get('ORG_NEW_ORGANIZATION_DESC')}</p>
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['orgaShortName']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['orgaLongName']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['orgaEmail']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_forward']}
</form>
