<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {if {array_key_exists array=$elements key='fot_cat_id'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['fot_cat_id']}
    {/if}
    {include 'sys-template-parts/form.input.tpl' data=$elements['fot_title']}
    {include 'sys-template-parts/form.editor.tpl' data=$elements['fop_text']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
