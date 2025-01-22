{$javascript}
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['changelog_tables']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['changelog_allow_deletion']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_changelog']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
