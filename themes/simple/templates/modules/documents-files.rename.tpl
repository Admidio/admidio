<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {if {array_key_exists array=$elements key='file_type'}}
        {include 'sys-template-parts/form.input.tpl' data=$elements['file_type']}
    {/if}
    {include 'sys-template-parts/form.input.tpl' data=$elements['previous_name']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['new_name']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['new_description']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_rename']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
