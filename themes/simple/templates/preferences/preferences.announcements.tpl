<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['announcements_module_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['announcements_per_page']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['maintainCategories']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_announcements']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
