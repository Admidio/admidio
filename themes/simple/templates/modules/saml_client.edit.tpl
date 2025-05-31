<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_AUTO_SETUP')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_metadata_url']}
            {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_metadata_setup']}
            {include 'sys-template-parts/form.custom-content.tpl' data=$elements['sso_saml_sso_staticsettings']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_NAME_PROPERTIES')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['smc_enabled']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_client_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_client_id']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_acs_url']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_slo_url']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_SIGNATURE_ENCRYPTION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.multiline.tpl' data=$elements['smc_x509_certificate']}

            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['smc_require_auth_signed']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['smc_validate_signatures']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['smc_sign_assertions']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['smc_encrypt_assertions']}

            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_assertion_lifetime']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['smc_allowed_clock_skew']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_USERDATA_ACCESS')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['smc_userid_field']}

            <div class="admidio-form-group admidio-form-custom-content row mb-3">
                <label class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_SSO_ATTRIBUTES')}
                </label>
                <div class="col-sm-9">
                    <div class="table-responsive">
                        <table class="table table-condensed" id="fieldsmap_table">
                            <thead>
                            <tr class="nosort">
                                <th style="width: 50%;">{$l10n->get('SYS_PROFILE_FIELD')}</th>
                                <th style="width: 43%;">{$l10n->get('SYS_SSO_ATTRIBUTE')}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="fieldsmap_tbody"></tbody>
                            <tfoot>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <input id="{$elements['sso_fields_all_other'].id}" name="{$elements['sso_fields_all_other'].id}" class="form-check-input focus-ring " type="checkbox" value="1" 
                                    {foreach $elements['sso_fields_all_other'].attributes as $itemvar}
                                        {$itemvar@key}="{$itemvar}"
                                    {/foreach} >
                                    <label class="form-check-label fw-normal" for="sso_fields_all_other"> {$l10n->get('SYS_SSO_ATTRIBUTES_ALLOTHER')}</label>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <a class="icon-text-link" href="javascript:addColumn_fieldsmap()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_FIELD')}</a>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <div class="form-text">{$l10n->get('SYS_SSO_ATTRIBUTES_DESC')}</div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="admidio-form-group admidio-form-custom-content row mb-3">
                <label class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_SSO_ROLESMAP')}
                </label>
                <div class="col-sm-9">
                    <div class="table-responsive">
                        <table class="table table-condensed" id="rolesmap_table">
                            <thead>
                            <tr class="nosort">
                                <th style="width: 50%;">{$l10n->get('SYS_ROLE')}</th>
                                <th style="width: 43%;">{$l10n->get('SYS_SSO_SAML_ROLE')}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="rolesmap_tbody"></tbody>
                            <tfoot>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <input id="{$elements['sso_roles_all_other'].id}" name="{$elements['sso_roles_all_other'].id}" class="form-check-input focus-ring " type="checkbox" value="1" 
                                    {foreach $elements['sso_roles_all_other'].attributes as $itemvar}
                                        {$itemvar@key}="{$itemvar}"
                                    {/foreach} >
                                    <label class="form-check-label fw-normal" for="sso_roles_all_other"> {$l10n->get('SYS_SSO_ROLES_ALLOTHER')}</label>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <a class="icon-text-link" href="javascript:addColumn_rolesmap()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ROLE')}</a>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <div class="form-text">{$l10n->get('SYS_SSO_ROLES_DESC')}</div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {include 'sys-template-parts/form.select.tpl' data=$elements['sso_roles_access']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
<script>
    $(".admidio-open-close-caret").click(function() {
        showHideBlock($(this));
    });
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
</script>
