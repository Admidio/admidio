<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_DESIGNATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['inf-1']}  {* Name *}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PROPERTIES')}</div>
        <div class="card-body">
            {foreach $elements as $key => $itemField}
                {if {string_contains haystack=$key needle="inf-"} && $key != "inf-1"}
                    {if $itemField.type == 'checkbox'}
                        {include 'sys-template-parts/form.checkbox.tpl' data=$itemField}
                    {elseif $itemField.type == 'multiline'}
                        {include 'sys-template-parts/form.multiline.tpl' data=$itemField}
                    {elseif $itemField.type == 'radio'}
                        {include 'sys-template-parts/form.radio.tpl' data=$itemField}
                    {elseif $itemField.type == 'select'}
                        {include 'sys-template-parts/form.select.tpl' data=$itemField}
                    {else}
                        {include 'sys-template-parts/form.input.tpl' data=$itemField}
                    {/if}
                {/if}
            {/foreach}
        </div>
    </div>
    {if {array_key_exists array=$elements key='item_copy_number'}}
        <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_INVENTORY_COPY_PREFERENCES')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.input.tpl' data=$elements['item_copy_number']}
                {include 'sys-template-parts/form.select.tpl' data=$elements['item_copy_field']}
            </div>
        </div>
    {/if}

    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
