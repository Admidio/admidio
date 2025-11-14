<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['groups_roles_module_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['groups_roles_members_per_page']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['groups_roles_default_configuration']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['groups_roles_show_former_members']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['groups_roles_export']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['groups_roles_edit_lists']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['editCategories']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_lists']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
