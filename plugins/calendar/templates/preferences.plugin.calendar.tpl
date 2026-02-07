<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['calendar_plugin_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['calendar_show_events']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['calendar_show_birthdays']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['calendar_show_birthdays_to_guests']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['calendar_show_birthday_icon']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['calendar_show_birthday_names']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['calendar_show_categories']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['calendar_show_categories_names']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['calendar_roles_view_plugin']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['calendar_roles_sql']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_calendar']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}