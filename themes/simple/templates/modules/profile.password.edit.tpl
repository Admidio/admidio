<script type="text/javascript">
    $("body").on("shown.bs.modal", ".modal", function() {
        $("#adm_password_edit_form").find("*").filter(":input:visible:first").focus()
        $("#adm_password_strength_minimum").css("margin-left", "calc(" + $("#adm_password_strength").css("width") + " / 4 * {$settings->getInt('password_min_strength')})");
        $("#new_password").keyup(function(e) {
            var result = zxcvbn(e.target.value, {$zxcvbnUserInputs});
            var cssClasses = ["bg-danger", "bg-danger", "bg-warning", "bg-info", "bg-success"];

            var progressBar = $("#adm_password_strength .progress-bar");
            progressBar.attr("aria-valuenow", result.score * 25);
            progressBar.css("width", result.score * 25 + "%");
            progressBar.removeClass(cssClasses.join(" "));
            progressBar.addClass(cssClasses[result.score]);
        });
    });
    $("#adm_password_edit_form").submit(formSubmit);
</script>

<div class="modal-header">
    <h3 class="modal-title">{$l10n->get('SYS_EDIT_PASSWORD')}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>
        <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {if {array_key_exists array=$elements key='old_password'}}
            {include 'sys-template-parts/form.input.tpl' data=$elements['old_password']}
            <hr />
        {/if}
        {include 'sys-template-parts/form.input.tpl' data=$elements['new_password']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['new_password_confirm']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    </form>
</div>
