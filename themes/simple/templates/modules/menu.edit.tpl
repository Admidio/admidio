<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['men_name']}
    {if {array_key_exists array=$elements key='men_name_intern'}}
        {include 'sys-template-parts/form.input.tpl' data=$elements['men_name_intern']}
    {/if}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['men_description']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['men_men_id_parent']}
    {if {array_key_exists array=$elements key='men_com_id'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['men_com_id']}
    {/if}
    {if {array_key_exists array=$elements key='menu_view'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['menu_view']}
    {/if}
    {if {array_key_exists array=$elements key='men_url'}}
        {include 'sys-template-parts/form.input.tpl' data=$elements['men_url']}
    {/if}
    {include 'sys-template-parts/form.input.tpl' data=$elements['men_icon']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
