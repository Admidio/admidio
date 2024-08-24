<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['captcha_type']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['captcha_fonts']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_width']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_lines_numbers']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_perturbation']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['captcha_background_image']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_background_color']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_text_color']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_line_color']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_charset']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['captcha_signature']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['captchaPreview']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save_captcha']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
