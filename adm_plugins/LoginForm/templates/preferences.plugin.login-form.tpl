<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['login_form_plugin_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_plugin_show_register_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_plugin_show_email_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_plugin_show_logout_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_plugin_enable_ranks']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['login_form_plugin_ranks']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_login_form']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}