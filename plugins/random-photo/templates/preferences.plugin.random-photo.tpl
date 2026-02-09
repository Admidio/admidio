<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['random_photo_plugin_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['random_photo_max_char_per_word']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['random_photo_max_width']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['random_photo_max_height']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['random_photo_albums']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['random_photo_album_photo_number']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['random_photo_show_album_link']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_random_photo']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}