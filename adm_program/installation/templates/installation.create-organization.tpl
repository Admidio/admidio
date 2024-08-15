<form {foreach $attributes as $attribute}
{$attribute@key}="{$attribute}"
{/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('INS_DATA_OF_ORGANIZATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['orga_shortname']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['orga_longname']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['orga_email']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['orga_timezone']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['previous_page']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['next_page']}
</form>
