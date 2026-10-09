<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {if isset($elements['item_name'])}
        {include 'sys-template-parts/form.input.tpl' data=$elements['item_name']}
    {else}
        {include 'sys-template-parts/form.select.tpl' data=$elements['reservation_item_uuid']}
    {/if}
    {include 'sys-template-parts/form.input.tpl' data=$elements['reservation_begin']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['reservation_end']}
    {if isset($elements['guest_first_name'])}
        {include 'sys-template-parts/form.input.tpl' data=$elements['guest_last_name']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['guest_first_name']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['guest_email']}
    {/if}
    {include 'sys-template-parts/form.input.tpl' data=$elements['reservation_comment']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
</form>
