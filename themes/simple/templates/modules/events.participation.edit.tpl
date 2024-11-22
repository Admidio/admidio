<script type="text/javascript">
    $("body").on("shown.bs.modal", ".modal", function() {
        $("#adm_events_participation_edit_form").find("*").filter(":input:visible:first").focus()
        $("#adm_events_participation_edit_form").submit(formSubmit);
        $("button[id=adm_button_attend]").click(function() {
            $("#adm_events_participation_edit_form").attr("action", "{$urlFormAction}" + "participate");
            $("#adm_events_participation_edit_form").submit();

        });
        $("button[id=adm_button_tentative]").click(function() {
            $("#adm_events_participation_edit_form").attr("action", "{$urlFormAction}" + "participate_maybe");
            $("#adm_events_participation_edit_form").submit();
        });
        $("button[id=adm_button_refuse]").click(function() {
            $("#adm_events_participation_edit_form").attr("action", "{$urlFormAction}" + "participate_cancel");
            $("#adm_events_participation_edit_form").submit();
        });
    });
</script>

<div class="modal-header">
    <h3 class="modal-title">{$l10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION')}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form {foreach $attributes as $attribute}
{$attribute@key}="{$attribute}"
{/foreach}>
    <div class="modal-body">
        <h5>{$eventHeadline}: {$eventPeriod}</h5>
            <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
            {include 'sys-template-parts/form.multiline.tpl' data=$elements['dat_comment']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['additional_guests']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
        <div class="btn-group" role="group">
            {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_attend']}
            {if {array_key_exists array=$elements key='adm_button_tentative'}}
                {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_tentative']}
            {/if}
            {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_refuse']}
        </div>
    </div>
</form>
