<script type="text/javascript">
    $("body").on("shown.bs.modal", ".modal", function() {
        $("#lastname").trigger("focus")
    });
    $("#adm_contacts_new_form").submit(formSubmit);
</script>

<div class="modal-header">
    <h3 class="modal-title">{$l10n->get('SYS_CREATE_CONTACT')}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <p class="lead">{$l10n->get('SYS_INPUT_FIRSTNAME_LASTNAME')}</p>
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>

        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['lastname']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['firstname']}
        {include 'sys-template-parts/form.button.tpl' data=$elements['btn_add']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
    </form>
</div>
