<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_module_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['pm_module_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_captcha_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_template']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_max_receiver']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_send_to_all_addresses']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_show_former']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['max_email_attachment_size']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_save_attachments']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_html_registered_users']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_delivery_confirmation']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_messages']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
