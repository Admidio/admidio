<script type="text/javascript">
    $(function(){
        var fieldsToHideOnSingleMode = "#mail_recipients_with_roles_group, #mail_into_to_group, #mail_number_recipients_group";
        if($("#mail_sending_mode").val() == 1) {
            $(fieldsToHideOnSingleMode).hide();
        }
        $("#mail_sending_mode").on("change", function() {
            if($("#mail_sending_mode").val() == 1) {
                $(fieldsToHideOnSingleMode).slideUp("slow");
            } else {
                $(fieldsToHideOnSingleMode).slideDown("slow");
            }
        });
    });
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_send_method']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_sender_email']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_sender_name']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_sending_mode']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_recipients_with_roles']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_into_to']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_number_recipients']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_host']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_smtp_auth']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_port']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_secure']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_authentication_type']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_user']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_password']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['send_test_email']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_email_dispatch']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
