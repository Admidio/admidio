<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_RESERVATION_REQUEST')}</div>
        <div class="card-body">
            {if isset($elements['item_name'])}
                {include 'sys-template-parts/form.input.tpl' data=$elements['item_name']}
            {else}
                {include 'sys-template-parts/form.select.tpl' data=$elements['reservation_item_uuid']}
            {/if}
            {include 'sys-template-parts/form.input.tpl' data=$elements['reservation_begin']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['reservation_end']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['reservation_comment']}
        </div>
    </div>
    {if isset($elements['guest_first_name'])}
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_CONTACT_DETAILS')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.input.tpl' data=$elements['guest_last_name']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['guest_first_name']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['guest_email']}
            </div>
        </div>
    {/if}
    {if {array_key_exists array=$elements key='adm_captcha_code'}}
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_CONFIRMATION_OF_INPUT')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.captcha.tpl' data=$elements['adm_captcha_code']}
            </div>
        </div>
    {/if}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
</form>
