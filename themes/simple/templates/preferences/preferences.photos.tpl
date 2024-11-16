<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['photo_module_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['photo_show_mode']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_albums_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_thumbs_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_thumbs_scale']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_show_width']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_show_height']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_image_text']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_image_text_size']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['photo_download_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['photo_keep_original']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['photo_ecard_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_ecard_scale']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['photo_ecard_template']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_photos']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
