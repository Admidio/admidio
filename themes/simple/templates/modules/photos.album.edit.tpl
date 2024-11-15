<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['pho_name']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['parent_album_uuid']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['pho_begin']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['pho_end']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['pho_photographers']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['pho_description']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['pho_locked']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
