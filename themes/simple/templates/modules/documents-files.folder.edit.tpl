<p class="lead">{$l10n->get('SYS_CREATE_FOLDER_DESC', [$parentFolderName])}</p>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['new_folder']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['new_description']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_create']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
