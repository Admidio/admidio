<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    {$lastCategory = ''}

    {foreach $elements as $key => $profileField}
        {if {array_key_exists array=$profileField key="category"}}
            {if $profileField.category != $lastCategory}
                {if $lastCategory != ''}
                    </div></div>
                {/if}
                {$lastCategory = {$profileField.category}}
                <div class="card admidio-field-group">
                    <div class="card-header">{$profileField.category}</div>
                    <div class="card-body">
            {/if}

            {if $profileField.type == 'checkbox'}
                {include 'sys-template-parts/form.checkbox.tpl' data=$profileField}
            {elseif $profileField.type == 'multiline'}
                {include 'sys-template-parts/form.multiline.tpl' data=$profileField}
            {elseif $profileField.type == 'radio'}
                {include 'sys-template-parts/form.radio.tpl' data=$profileField}
            {elseif $profileField.type == 'select'}
                {include 'sys-template-parts/form.select.tpl' data=$profileField}
            {else}
                {include 'sys-template-parts/form.input.tpl' data=$profileField}
            {/if}
        {/if}
    {/foreach}
    </div></div>

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

    {if isset($userCreatedName)}
        {include file="sys-template-parts/system.info-create-edit.tpl"}
    {/if}
</form>
