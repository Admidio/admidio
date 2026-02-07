<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['birthday_plugin_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['birthday_show_names_extern']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['birthday_show_names']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['birthday_show_age']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['birthday_show_age_salutation']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['birthday_show_notice_none']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['birthday_show_past']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['birthday_show_future']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['birthday_show_display_limit']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['birthday_show_email_extern']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['birthday_roles_view_plugin']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['birthday_roles_sql']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['birthday_sort_sql']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_birthday']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}