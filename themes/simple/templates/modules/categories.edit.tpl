<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['cat_name']}
    {if {array_key_exists array=$elements key='adm_categories_view_right'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['adm_categories_view_right']}
    {/if}
    {if {array_key_exists array=$elements key='adm_categories_edit_right'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['adm_categories_edit_right']}
    {/if}
    {if {array_key_exists array=$elements key='adm_administrators'}}
        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_administrators']}
    {/if}
    {if {array_key_exists array=$elements key='show_in_several_organizations'}}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['show_in_several_organizations']}
    {/if}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['cat_default']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
