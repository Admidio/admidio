<form {foreach $attributes as $attribute}
{$attribute@key}="{$attribute}"
{/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('INS_DATABASE_LOGIN')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['adm_db_type']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_db_host']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_db_port']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_db_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_db_username']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_db_password']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_table_prefix']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_previous_page']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_next_page']}
</form>
