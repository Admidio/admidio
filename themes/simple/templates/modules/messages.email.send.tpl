<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {if {array_key_exists array=$elements key='userUuidList'}}
        {include 'sys-template-parts/form.input.tpl' data=$elements['userUuidList']}
    {/if}
    {if {array_key_exists array=$elements key='list_uuid'}}
        {include 'sys-template-parts/form.input.tpl' data=$elements['list_uuid']}
    {/if}

    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_CONTACT_DETAILS')}</div>
        <div class="card-body">


            {include 'sys-template-parts/form.select.tpl' data=$elements['msg_to']}
            <hr />
            {include 'sys-template-parts/form.input.tpl' data=$elements['sender_name']}
            {if !$validLogin || $settings->getInt('mail_sender_mode') != 2}
                {if $possibleEmails > 1}
                    {include 'sys-template-parts/form.select.tpl' data=$elements['sender_email']}
                {else}
                    {include 'sys-template-parts/form.input.tpl' data=$elements['sender_email']}
                {/if}
            {/if}
            {if {array_key_exists array=$elements key='carbon_copy'}}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['carbon_copy']}
            {/if}
            {if {array_key_exists array=$elements key='delivery_confirmation'}}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['delivery_confirmation']}
            {/if}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_MESSAGE')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['msg_subject']}
            {if $validLogin && $settings->getBool('mail_html_registered_users')}
                {include 'sys-template-parts/form.editor.tpl' data=$elements['msg_body']}
            {else}
                {include 'sys-template-parts/form.multiline.tpl' data=$elements['msg_body']}
            {/if}
            {if {array_key_exists array=$elements key='btn_add_attachment'}}
                {include 'sys-template-parts/form.file.tpl' data=$elements['btn_add_attachment']}
            {/if}
        </div>
    </div>
    {if {array_key_exists array=$elements key='adm_captcha_code'}}
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_CONFIRMATION_OF_INPUT')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.captcha.tpl' data=$elements['adm_captcha_code']}
            </div>
        </div>
    {/if}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_send']}
</form>
