<script type="text/javascript">
    /* Function to handle the visibility of fields based on the corresponding option */
    $(function(){
        /* Keeper edit */
        if(!$("#inventory_allow_keeper_edit").is(":checked")) {
            $("#inventory_allowed_keeper_edit_fields_group").slideUp("slow");
        }
        $("#inventory_allow_keeper_edit").on("change", function() {
            if(!$("#inventory_allow_keeper_edit").is(":checked")) {
                $("#inventory_allowed_keeper_edit_fields_group").slideUp("slow");
            } else {
                $("#inventory_allowed_keeper_edit_fields_group").slideDown("slow");
            }
        });
        /* Profile view */
        if(!$("#inventory_profile_view_enabled").is(":checked")) {
            $("#inventory_profile_view_group").slideUp("slow");
        }
        $("#inventory_profile_view_enabled").on("change", function() {
            if(!$("#inventory_profile_view_enabled").is(":checked")) {
                $("#inventory_profile_view_group").slideUp("slow");
            } else {
                $("#inventory_profile_view_group").slideDown("slow");
            }
        });
    });
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_module_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_items_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_field_history_days']}
    {include 'sys-template-parts/form.separator.tpl' data=$elements['inventory_separator_general_settings']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_show_obsolete_select_field_options']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_items_disable_borrowing']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_system_field_names_editable']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_allow_keeper_edit']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_allowed_keeper_edit_fields']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_current_user_default_keeper']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_allow_negative_numbers']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_decimal_places']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_field_date_time_format']}
    {include 'sys-template-parts/form.separator.tpl' data=$elements['inventory_separator_profile_view_settings']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_profile_view_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_profile_view']}
    {include 'sys-template-parts/form.separator.tpl' data=$elements['inventory_separator_export_settings']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_export_filename']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_add_date']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_inventory']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}