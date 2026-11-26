<script type="text/javascript">
    $(function(){
        var $form = $("#adm_preferences_form_email_dispatch");
        // only the carets of this panel, the preferences page shows several panels at once
        $form.find(".admidio-open-close-caret").on("click", function() {
            showHideBlock($(this));
        });
        addCopyButtons($form.find(".copy-container"), "{$l10n->get('SYS_COPY_CLIPBOARD')}");

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

        // per provider: its SMTP server, whether it offers application access and its own settings
        var smtpOAuthProviders = {$smtpOAuthProviders};
        var smtpOAuthSettingIds = Object.values(smtpOAuthProviders).flatMap(function(provider) { return provider.settings; });
        var smtpSendMethods = ["SMTP", "SMTP_OAUTH"];
        function showSmtpAuthenticationSettings() {
            var sendMethod = $("input[name='mail_send_method']:checked").val();
            var provider = smtpOAuthProviders[$("#mail_smtp_oauth_provider").val()] || { appAccess: false, settings: [] };
            var appAccess = provider.appAccess && $("#mail_smtp_oauth_grant_type").val() === "client_credentials";
            $("#mail_smtp_auth_group, #mail_smtp_authentication_type_group, #mail_smtp_password_group").toggle(sendMethod === "SMTP");
            $(".smtp-oauth-setting").toggle(sendMethod === "SMTP_OAUTH");
            $("#mail_smtp_oauth_grant_type_group").toggle(sendMethod === "SMTP_OAUTH" && provider.appAccess);
            smtpOAuthSettingIds.forEach(function(id) {
                $("#" + id + "_group").toggle(sendMethod === "SMTP_OAUTH" && provider.settings.includes(id));
            });
            $("#smtp_oauth_authorization").toggle(sendMethod === "SMTP_OAUTH" && !appAccess);
        }
        if(!smtpSendMethods.includes($("input[name='mail_send_method']:checked").val())) {
            $("#smtp_settings").hide();
        }
        showSmtpAuthenticationSettings();
        $("input[name='mail_send_method']").on("change", function() {
            if(smtpSendMethods.includes($(this).val())) {
                $("#smtp_settings").slideDown("slow");
            } else {
                $("#smtp_settings").slideUp("slow");
            }
            showSmtpAuthenticationSettings();
        });
        $("#mail_smtp_oauth_grant_type").on("change", showSmtpAuthenticationSettings);
        // Choosing a provider fills in its server, unless the administrator entered another one.
        var previousProvider = $("#mail_smtp_oauth_provider").val();
        $("#mail_smtp_oauth_provider").on("change", function() {
            var smtp = (smtpOAuthProviders[$(this).val()] || { }).smtp || { };
            var previousHost = ((smtpOAuthProviders[previousProvider] || { }).smtp || { }).host || "";
            var host = $("#mail_smtp_host").val();
            if (smtp.host && (host === "" || host === previousHost)) {
                $("#mail_smtp_host").val(smtp.host);
                $("#mail_smtp_secure").val(smtp.secure || "");
                $("#mail_smtp_port").val(smtp.port || "");
            }
            previousProvider = $(this).val();
            showSmtpAuthenticationSettings();
        });
        $("#mail_smtp_secure").on("change", function() {
            var smtpPorts = {
                "": 25,
                "ssl": 465,
                "tls": 587
            };
            $("#mail_smtp_port").val(smtpPorts[$(this).val()]);
        });

        // The account buttons act at once, outside the form. Connecting reads the saved settings, so
        // it waits until there are no unsaved changes; the other actions warn that they are lost.
        var initialFormState = $form.serialize();
        function formIsDirty() {
            return $form.serialize() !== initialFormState;
        }
        function updateSmtpOAuthActions() {
            var dirty = formIsDirty();
            $form.find(".admidio-smtp-oauth-action[data-needs-saved-settings]").prop("disabled", dirty);
            $("#adm_smtp_oauth_save_hint").toggle(dirty);
        }
        $form.on("input change", updateSmtpOAuthActions);
        $form.find(".admidio-smtp-oauth-action").on("click", function() {
            var data = { adm_csrf_token: $(this).data("csrf-token") };
            var url = $(this).data("url");
            if (!formIsDirty()) {
                redirectPost(url, data);
                return;
            }
            if ($(this).data("needs-saved-settings")) {
                return;
            }
            messageBox(
                "{$l10n->get('ORG_NOT_SAVED_SETTINGS_LOST')}" + "<br>" + "{$l10n->get('ORG_NOT_SAVED_SETTINGS_CONTINUE')}",
                undefined, undefined,
                "yes-no",
                "redirectPost(" + JSON.stringify(url) + ", " + JSON.stringify(data) + ");"
            );
        });
    });
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    {include 'sys-template-parts/form.separator.tpl' data=$elements['email_dispatch_properties']}
    <div id="email_dispatch_properties_contents">
        {include 'sys-template-parts/form.select.tpl' data=$elements['mail_sender_mode']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['mail_sender_email']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['mail_sender_name']}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_send_to_all_addresses']}
        {include 'sys-template-parts/form.select.tpl' data=$elements['mail_sending_mode']}
        {include 'sys-template-parts/form.select.tpl' data=$elements['mail_recipients_with_roles']}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_into_to']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['mail_number_recipients']}
    </div>

    {include 'sys-template-parts/form.separator.tpl' data=$elements['email_dispatch_connection']}
    <div id="email_dispatch_connection_contents">
        {include 'sys-template-parts/form.radio.tpl' data=$elements['mail_send_method']}
        <div id="smtp_settings">
            <div class="smtp-oauth-setting">
                {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_oauth_provider']}
            </div>
            {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_host']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_port']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_secure']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['mail_smtp_auth']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_authentication_type']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_user']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_password']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['mail_smtp_oauth_grant_type']}
            {* the settings of the providers are declared like those of a plugin and routed by their type *}
            {foreach $smtpOAuthSettingIds as $settingId}
                {if $elements[$settingId].type eq 'checkbox'}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements[$settingId]}
                {elseif $elements[$settingId].type eq 'select'}
                    {include 'sys-template-parts/form.select.tpl' data=$elements[$settingId]}
                {else}
                    {include 'sys-template-parts/form.input.tpl' data=$elements[$settingId]}
                {/if}
            {/foreach}
            <div class="smtp-oauth-setting">
                {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_client_id']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['mail_smtp_oauth_client_secret']}
                {if isset($elements['smtp_oauth_client_secret_remove'])}
                    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['smtp_oauth_client_secret_remove']}
                {/if}
                <div id="smtp_oauth_authorization">
                    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['smtp_oauth_redirect_uri']}
                    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['smtp_oauth_account']}
                </div>
            </div>
        </div>
        {include 'sys-template-parts/form.custom-content.tpl' data=$elements['send_test_email']}
    </div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_email_dispatch']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
