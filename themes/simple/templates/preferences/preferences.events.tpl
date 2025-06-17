<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['events_module_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['events_view']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['events_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['events_clamp_text_lines']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['events_ical_export_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['events_show_map_link']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['events_list_configuration']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['events_save_cancellations']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['events_may_take_part']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['editCalendars']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['events_rooms_enabled']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['editRooms']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_events']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
