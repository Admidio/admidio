<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['homepage_logout']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['homepage_login']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['enable_rss']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_cookie_note']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_search_similar']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['system_show_create_edit']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_url_data_protection']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_url_imprint']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_js_editor_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_browser_update_check']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_common']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
