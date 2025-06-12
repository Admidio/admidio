<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['theme']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['theme_fallback']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['color_primary']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['color_secondary']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['additional_styles_file']}
    
    {include 'sys-template-parts/form.input.tpl' data=$elements['logo_file']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['favicon_file']}

    {include 'sys-template-parts/form.input.tpl' data=$elements['clamp_text_lines']}

    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_design']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
