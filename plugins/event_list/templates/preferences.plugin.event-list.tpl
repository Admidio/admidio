<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['event_list_plugin_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['event_list_events_count']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['event_list_show_event_date_end']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['event_list_show_preview_chars']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['event_list_show_full_description']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['event_list_chars_before_linebreak']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['event_list_displayed_categories']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_event_list']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}