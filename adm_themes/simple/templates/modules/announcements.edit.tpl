<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['ann_headline']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['ann_cat_id']}
    {include 'sys-template-parts/form.editor.tpl' data=$elements['ann_description']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
