<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['theme']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['theme_fallback']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_color_primary']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_color_secondary']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_color_tertiary']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_color_background']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_color_text']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_additional_styles_file']}

    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_logo_file']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_logo_file_max_height']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_favicon_file']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['theme_admidio_headline']}

    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_design']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
