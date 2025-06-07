<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['weblinks_module_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['weblinks_per_page']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['weblinks_target']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['weblinks_redirect_seconds']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['editCategories']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_links']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
