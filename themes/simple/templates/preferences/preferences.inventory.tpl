<script type="text/javascript">
    /* function to initialize the visibility of dependent fields in the inventory preferences form */
    function initializeInventoryPreferencesVisibility(direct=false, directModule=false) {
        // special case:
        if($("#inventory_module_enabled").val() == 5) {
            directModule ? $("#inventory_visible_for_group").show() : $("#inventory_visible_for_group").slideDown("slow");
        } else {
            directModule ? $("#inventory_visible_for_group").hide() : $("#inventory_visible_for_group").slideUp("slow");
        }
        if(!$("#inventory_item_picture_enabled").is(":checked")) {
            direct ? $("#inventory_item_picture_storage_group").hide() : $("#inventory_item_picture_storage_group").slideUp("slow");
            direct ? $("#inventory_item_picture_width_group").hide() : $("#inventory_item_picture_width_group").slideUp("slow");
            direct ? $("#inventory_item_picture_height_group").hide() : $("#inventory_item_picture_height_group").slideUp("slow");
        }
        if($("#inventory_item_picture_storage").val() == 1) {
            direct ? $("#inventory_item_picture_width_group").show() : $("#inventory_item_picture_width_group").slideDown("slow");
            direct ? $("#inventory_item_picture_height_group").show() : $("#inventory_item_picture_height_group").slideDown("slow");
        } else {
            direct ? $("#inventory_item_picture_width_group").hide() : $("#inventory_item_picture_width_group").slideUp("slow");
            direct ? $("#inventory_item_picture_height_group").hide() : $("#inventory_item_picture_height_group").slideUp("slow");
        }
        if(!$("#inventory_allow_keeper_edit").is(":checked")) {
            direct ? $("#inventory_allowed_keeper_edit_fields_group").hide() : $("#inventory_allowed_keeper_edit_fields_group").slideUp("slow");
        }
        if(!$("#inventory_profile_view_enabled").is(":checked")) {
            direct ? $("#inventory_profile_view_group").hide() : $("#inventory_profile_view_group").slideUp("slow");
        }
    }
    /* Function to handle the visibility of fields based on the corresponding option */
    $(function(){
        /* visible for */
        $("#inventory_module_enabled").on("change", function() {
            if($("#inventory_module_enabled").val() == 5) {
                $("#inventory_visible_for_group").slideDown("slow");
            } else {
                $("#inventory_visible_for_group").slideUp("slow");
            }
        });
        /* Item pictures */
        $("#inventory_item_picture_enabled").on("change", function() {
            if(!$("#inventory_item_picture_enabled").is(":checked")) {
                $("#inventory_item_picture_storage_group").slideUp("slow");
                $("#inventory_item_picture_width_group").slideUp("slow");
                $("#inventory_item_picture_height_group").slideUp("slow");
            } else {
                $("#inventory_item_picture_storage_group").slideDown("slow");
                if($("#inventory_item_picture_storage").val() == 1) {
                    $("#inventory_item_picture_width_group").slideDown("slow");
                    $("#inventory_item_picture_height_group").slideDown("slow");
                } else {
                    $("#inventory_item_picture_width_group").slideUp("slow");
                    $("#inventory_item_picture_height_group").slideUp("slow");
                }
            }
        });
        /* Item pictures resolution */
        $("#inventory_item_picture_storage").on("change", function() {
            if($("#inventory_item_picture_storage").val() == 1) {
                $("#inventory_item_picture_width_group").slideDown("slow");
                $("#inventory_item_picture_height_group").slideDown("slow");
            } else {
                $("#inventory_item_picture_width_group").slideUp("slow");
                $("#inventory_item_picture_height_group").slideUp("slow");
            }
        });
        /* Keeper edit */
        $("#inventory_allow_keeper_edit").on("change", function() {
            if(!$("#inventory_allow_keeper_edit").is(":checked")) {
                $("#inventory_allowed_keeper_edit_fields_group").slideUp("slow");
            } else {
                $("#inventory_allowed_keeper_edit_fields_group").slideDown("slow");
            }
        });
        /* Profile view */
        $("#inventory_profile_view_enabled").on("change", function() {
            if(!$("#inventory_profile_view_enabled").is(":checked")) {
                $("#inventory_profile_view_group").slideUp("slow");
            } else {
                $("#inventory_profile_view_group").slideDown("slow");
            }
        });

        // wait for the form to be fully visible
        var interval = setInterval(function() {
            if (!$("#inventory_item_picture_enabled").is(":hidden") && !$("#inventory_profile_view_group").is(":hidden") && !$("#inventory_allowed_keeper_edit_fields_group").is(":hidden")) {
                clearInterval(interval);
                // now we can initialize the visibility of the fields
                initializeInventoryPreferencesVisibility();
            }
        }, 100);

        // reinitialize visibility when the inventory module is enabled field changes
        let prevInventoryModuleEnabled = $("#inventory_module_enabled").val();
        $("#inventory_module_enabled").on("change", function() {
            const currentVal = $(this).val();
            let directModule = false;
            if (prevInventoryModuleEnabled != 5 && currentVal != 5) {
                // direct animation for "inventory_visible_for" field only if the value did not change to/from "5 - For module administrators and role members"
                directModule = true;
            }
            initializeInventoryPreferencesVisibility(true, directModule);
            prevInventoryModuleEnabled = currentVal;
        });
    });
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_module_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_visible_for']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_items_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_field_history_days']}
    {include 'sys-template-parts/form.separator.tpl' data=$elements['inventory_separator_general_settings']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inventory_item_picture_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['inventory_item_picture_storage']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_item_picture_width']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['inventory_item_picture_height']}
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