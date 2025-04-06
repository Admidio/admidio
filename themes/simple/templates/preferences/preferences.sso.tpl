<script>
    $(".copy-container").each(function () {
        let $element = $(this);

        // Wrap the element in a flex container to maintain full width
        let $wrapper = $("<div>").css({
            "display": "flex",
            "align-items": "center",
            "width": "100%" // Ensure the wrapper is full-width
        });

        // Ensure input and textarea elements keep their full width
        if ($element.is("input, textarea, select")) {
            $element.css({
                "flex": "1", // Take up all available space
                "width": "100%" // Explicitly set full width
            });
        }

        // Create the copy button
        let $copyButton = $("<div>")
            .addClass("copy-btn")
            .html('<i class="bi bi-copy"></i>') // Bootstrap copy icon
            .attr("title", "{$l10n->get('SYS_COPY_CLIPBOARD')}") // Tooltip text
            .css({
                "cursor": "pointer",
                "margin-left": "4px", // Space between text and button
                "padding": "0px",
            });

        // Wrap the element and insert the button
        $element.wrap($wrapper);
        $element.after($copyButton);

        // Click event to copy text
        $copyButton.on("click", function () {
            let textToCopy = "";

            // Determine how to get the value based on the element type
            if ($element.is("input, textarea, select")) {
                textToCopy = $element.val(); // Get value for form elements
            } else {
                textToCopy = $element.text().trim(); // Get text for divs or spans
            }

            // Copy text to clipboard
            navigator.clipboard.writeText(textToCopy).then(() => {
                // Change icon to indicate success
                $copyButton.html('<i class="bi bi-clipboard-check"></i>');

                // Reset icon after 1.5 seconds
                setTimeout(() => {
                    $copyButton.html('<i class="bi bi-copy"></i>');
                }, 1500);
            }).catch(err => {
                console.error("Failed to copy:", err);
            });
        });
    });

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
</script>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['sso_keys']}

{* ********************************************************************************** 
 * SAML settings 
 * **********************************************************************************}

    {$elements['sso_saml_settings'].content}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['sso_saml_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['sso_saml_entity_id']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['sso_saml_signing_key']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['sso_saml_encryption_key']}

    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['sso_saml_want_requests_signed']}

    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['sso_saml_sso_staticsettings']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['sso_saml_clients']}

    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_sso']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
