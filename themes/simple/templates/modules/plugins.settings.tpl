{*
    The settings of one plugin, shown in a dialog on the plugin administration. The body is the very
    panel the preferences page shows, so the form inside was built to post to the preferences page.
    Its action is redirected here, because that answer sends the administrator to the preferences and
    away from the list they opened the dialog from.
*}
<script type="text/javascript">
    $("#adm_modal form").attr("action", "{$pluginSaveUrl}").submit(formSubmit);
    $("body").on("shown.bs.modal", "#adm_modal", function() {
        $("#adm_modal form").find("*").filter(":input:visible:first").focus();
    });
</script>

<div class="modal-header">
    <h3 class="modal-title">{$pluginName}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CANCEL')}"></button>
</div>
<div class="modal-body">
    {$pluginPanelBody nofilter}
</div>
