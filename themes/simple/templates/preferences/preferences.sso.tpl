<script>
    // only the carets of this panel, the preferences page shows several panels at once
    $("#adm_preferences_form_sso .admidio-open-close-caret").click(function() {
        showHideBlock($(this));
    });
    addCopyButtons($("#adm_preferences_form_sso .copy-container"), "{$l10n->get('SYS_COPY_CLIPBOARD')}");

    // Whenever a different certificate is selected, update the textbox showing the cert for copying
    $('#sso_saml_signing_key').on('change', function() {
        var selectedOption = $(this).find(':selected');
        var certificateData = selectedOption.data('global');
        $('#wrapper_certificate').text(certificateData || '');
    });

    // Trigger change event on page load to set initial value
    $('#sso_saml_signing_key').trigger('change');


    // Show/hide saml and oidc controls depending on the saml/oidc enabled checkboxes
    $('#sso_saml_enabled').on('change', function() {
        if ($('#sso_saml_enabled').is(':checked')) {
            $('.admidio-form-group:has(.if-saml-enabled)').show();
        } else {
            $('.admidio-form-group:has(.if-saml-enabled)').hide();
        }
    });
    $('#sso_saml_enabled').trigger('change');
    $('#sso_oidc_enabled').on('change', function() {
        if ($('#sso_oidc_enabled').is(':checked')) {
            $('.admidio-form-group:has(.if-oidc-enabled)').show();
        } else {
            $('.admidio-form-group:has(.if-oidc-enabled)').hide();
        }
    });
    $('#sso_oidc_enabled').trigger('change');

    $(function() {
        const $ssoForm = $("#adm_preferences_form_sso");
        const initialSsoFormState = $ssoForm.serialize();

        const $keyAdminButtonTemplate = $("#sso_key_admin_button_template");

        $(".sso-key-select").each(function() {
            const $select = $(this);

            const $button = $keyAdminButtonTemplate
                .clone()
                .removeAttr("id")
                .attr("title", "{$l10n->get('SYS_SSO_KEY_ADMIN')}")
                .attr("aria-label","{$l10n->get('SYS_SSO_KEY_ADMIN')}");

            $select.wrap(
                $("<div>").addClass("d-flex align-items-start gap-2")
            );
            $button.insertAfter($select);
        });

        $("#sso_key_admin_button_container").remove();

        // Navigate away from the preferences, but warn about unsaved changes beforehand.
        // Without any modification the target page is opened directly, so an untouched form
        // never triggers the warning.
        function leaveSsoPreferences(targetUrl) {
            if (!targetUrl) {
                return;
            }

            if ($ssoForm.serialize() === initialSsoFormState) {
                window.location.href = targetUrl;
                return;
            }

            messageBox(
                "{$l10n->get('ORG_NOT_SAVED_SETTINGS_LOST')}" + "<br>" + "{$l10n->get('ORG_NOT_SAVED_SETTINGS_CONTINUE')}",
                undefined, undefined,
                "yes-no",
                "window.location.href=" + JSON.stringify(targetUrl) + ";"
            );
        }

        $(".sso-key-admin-button").on("click", function() {
            leaveSsoPreferences("{$ssoKeyAdminUrl}");
        });

        $(".sso-client-admin-button").on("click", function() {
            leaveSsoPreferences($(this).data("href"));
        });
    });

</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    {* template button that links to the key administration, will be copied/inserted/deleted by JS *}
    <div id="sso_key_admin_button_container" class="d-none">
        {include 'sys-template-parts/form.button.tpl' data=$elements['sso_key_admin_button_template']}
    </div>
    
    {include 'sys-template-parts/form.description.tpl' data=$elements['sso_explanation']}

{* ********************************************************************************** 
 * SAML settings 
 * **********************************************************************************}

    {include 'sys-template-parts/form.separator.tpl' data=$elements['sso_saml_settings']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['sso_saml_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_entity_id']}
    {include 'sys-template-parts/form.static-subinformation.tpl' data=$elements['sso_saml_sso_staticsettings']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['sso_saml_clients']}

    {include 'sys-template-parts/form.separator.tpl' data=$elements['sso_saml_advanced_settings']}
    <div id="sso_saml_advanced" {if !empty($elements['sso_saml_advanced_settings'].collapsed)} style="display: none;" {/if}>
        {include 'sys-template-parts/form.select.tpl' data=$elements['sso_saml_signing_key']}
        {include 'sys-template-parts/form.select.tpl' data=$elements['sso_saml_encryption_key']}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['sso_saml_want_requests_signed']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_metadata_valid_days']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_metadata_cache_hours']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_organization_name']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_organization_url']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_contact_email']}
    </div>



{* ********************************************************************************** 
 * OIDC settings 
 * **********************************************************************************}

    {include 'sys-template-parts/form.separator.tpl' data=$elements['sso_oidc_settings']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['sso_oidc_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['sso_oidc_issuer_url']}
    {include 'sys-template-parts/form.static-subinformation.tpl' data=$elements['sso_oidc_sso_staticsettings']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['sso_oidc_clients']}

    {include 'sys-template-parts/form.separator.tpl' data=$elements['sso_oidc_advanced_settings']}
    <div id="sso_oidc_advanced" {if !empty($elements['sso_oidc_advanced_settings'].collapsed)} style="display: none;" {/if}>
        {include 'sys-template-parts/form.select.tpl' data=$elements['sso_oidc_signing_key']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_oidc_auth_code_lifetime']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_oidc_access_token_lifetime']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['sso_oidc_refresh_token_lifetime']}
    </div>
    

{* ********************************************************************************** 
 * Advanced general settings 
 * **********************************************************************************}

    {include 'sys-template-parts/form.separator.tpl' data=$elements['sso_advanced_settings']}
    <div id="sso_advanced_settings_contents" {if !empty($elements['sso_advanced_settings'].collapsed)} style="display: none;" {/if}>
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['sso_allow_private_network']}
    </div>


    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_sso']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
