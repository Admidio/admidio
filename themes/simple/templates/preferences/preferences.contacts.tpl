<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['contacts_list_configuration']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['contacts_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['contacts_field_history_days']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['contacts_show_all']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['contacts_user_relations_enabled']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['userRelations']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_contacts']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
