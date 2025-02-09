<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['forum_module_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['forum_topics_per_page']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['forum_posts_per_page']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_forum']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
