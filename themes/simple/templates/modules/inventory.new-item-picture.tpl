<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['item_picture_current']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['item_picture_new']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
</form>
