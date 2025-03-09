<p class="lead">{$description}</p>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['adm_destination_folder_uuid']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_btn_move']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
