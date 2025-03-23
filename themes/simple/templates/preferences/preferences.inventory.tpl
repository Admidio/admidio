<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_module_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_items_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_field_history_days']}
    {include 'sys-template-parts/form.seperator.tpl' data=$elements['inventory_seperator_general_settings']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_system_field_names_editable']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_allow_keeper_edit']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_allowed_keeper_edit_fields']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_current_user_default_keeper']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_allow_negative_numbers']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_decimal_places']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_field_date_time_format']}
    {include 'sys-template-parts/form.seperator.tpl' data=$elements['inventory_seperator_profile_view_settings']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_profile_view']}
    {include 'sys-template-parts/form.seperator.tpl' data=$elements['inventory_seperator_export_settings']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_export_filename']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_add_date']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_inventory']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}