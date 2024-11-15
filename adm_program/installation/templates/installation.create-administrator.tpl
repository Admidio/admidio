<form {foreach $attributes as $attribute}
{$attribute@key}="{$attribute}"
{/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('INS_DATA_OF_ADMINISTRATOR')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['user_last_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['user_first_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['user_email']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['user_login']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['user_password']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['user_password_confirm']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_previous_page']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_next_page']}
</form>
