<script type="text/javascript">
    $("#downloadButton").on("click", function () {
        event.preventDefault(); // Prevent default form submission
        var form = $("#adm_password_form");

        // Show loading indicator
        var submitButton = $(this);
        var submitButtonID = submitButton.attr("id");
        var submitButtonIcon = submitButton.find("i");
        var iconClass = submitButtonIcon.attr("class");
        var formAlert = $("#" + form.attr("id") + " .form-alert");
        submitButtonIcon.attr("class", "spinner-border spinner-border-sm");
        submitButton.attr("disabled", true);
        formAlert.hide();

        // Track if an error was detected
        let errorDetected = false;

        // Handle download completion by downloading to a hidden <iframe> and checking that 
        // rather than doing an AJAX call with jquery (detect success or error)
        $("#downloadFrame").on("load", function () {
            var iframeDoc = this.contentDocument || this.contentWindow.document;
            var responseText = iframeDoc.body.innerText.trim();
            var jsonResponse = JSON.parse(responseText);

            try {
                if (jsonResponse.status === "error") {
                    errorDetected = true;

                    returnMessage = jsonResponse.message;
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.html("<i class=\"bi bi-exclamation-circle-fill\"></i> " + returnMessage);
                    formAlert.fadeIn();
                }
            } catch (e) {
                // If JSON parsing fails, assume it's a successful file download
            }

            // Reset button state
            submitButton.attr("disabled", false);
            submitButtonIcon.attr("class", iconClass);
        });

        // Submit the form
        form.submit();

        // Fallback: Assume success after 3 seconds unless an error is detected
        setTimeout(function () {
            if (!errorDetected) {
                $("#adm_modal").modal("hide");
                $(".form-alert").hide("slow");
                submitButton.attr("disabled", false);
                submitButtonIcon.attr("class", iconClass);
            }
        }, 2500);
    });
    $("body").on("shown.bs.modal", ".modal", function() {
        $("#adm_password_form").find("*").filter(":input:visible:first").focus()
    });
</script>

<div class="modal-header">
    <h3 class="modal-title">{$l10n->get('SYS_SSO_EXPORT_PASSWORD')}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach} target="downloadFrame">
        <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['key_password']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
        {include 'sys-template-parts/form.button.tpl' data=$elements['downloadButton']}
    </form>
    <!-- Hidden iframe that will handle the file download -->
    <iframe id="downloadFrame" name="downloadFrame" style="display: none;"></iframe>
</div>
