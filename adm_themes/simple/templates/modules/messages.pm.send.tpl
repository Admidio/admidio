<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}

    {if $userUUID == ''}
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_CONTACT_DETAILS')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.select.tpl' data=$elements['msg_to']}
            </div>
        </div>
    {else}
        {include 'sys-template-parts/form.input.tpl' data=$elements['msg_to']}
    {/if}

    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_MESSAGE')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['msg_subject']}
            {include 'sys-template-parts/form.multiline.tpl' data=$elements['msg_body']}
        </div>
    </div>

    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_send']}
</form>
