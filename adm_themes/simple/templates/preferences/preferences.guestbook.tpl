<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['enable_guestbook_module']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['guestbook_entries_per_page']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['enable_guestbook_captcha']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['enable_guestbook_moderation']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['enable_gbook_comments4all']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['enable_intial_comments_loading']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['flooding_protection_time']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_guestbook']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
