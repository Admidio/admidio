<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['registration_enable_module']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['registration_manual_approval']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['registration_enable_captcha']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['registration_adopt_all_data']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['registration_send_notification_email']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save_registration']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
