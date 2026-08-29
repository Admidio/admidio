<script type="text/javascript">
    $(function(){
        var fieldsToHideOnSingleMode = "#mail_recipients_with_roles_group, #mail_into_to_group, #mail_number_recipients_group";
        var oauthFields = "#mail_smtp_oauth_token_url_group, #mail_smtp_oauth_client_id_group, #mail_smtp_oauth_client_secret_group, #mail_smtp_oauth_scope_group, #mail_smtp_oauth_grant_type_group, #mail_smtp_oauth_refresh_token_group, #mail_smtp_oauth_user_group";
        function toggleOAuthFields() {
            if($("#mail_smtp_oauth_enabled").is(":checked")) {
                $(oauthFields).slideDown("slow");
            } else {
                $(oauthFields).slideUp("slow");
            }
        }
        if($("#mail_sending_mode").val() == 1) {
            $(fieldsToHideOnSingleMode).hide();
        }
        toggleOAuthFields();
        $("#mail_sending_mode").on("change", function() {
            if($("#mail_sending_mode").val() == 1) {
                $(fieldsToHideOnSingleMode).slideUp("slow");
            } else {
                $(fieldsToHideOnSingleMode).slideDown("slow");
            }
        });
        $("#mail_smtp_oauth_enabled").on("change", toggleOAuthFields);
    });
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_send_method']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_sender_mode']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_sender_email']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_sender_name']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_send_to_all_addresses']}
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
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_smtp_oauth_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_token_url']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_client_id']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_client_secret']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_scope']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_oauth_grant_type']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_refresh_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_user']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['send_test_email']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_email_dispatch']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
