<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['who_is_online_plugin_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['who_is_online_time_still_active']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['who_is_online_show_visitors']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['who_is_online_show_members_to_visitors']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['who_is_online_show_self']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['who_is_online_show_users_side_by_side']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_who_is_online']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}