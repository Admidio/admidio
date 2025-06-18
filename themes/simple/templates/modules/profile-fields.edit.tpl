<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_DESIGNATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['usf_name']}
            {if {array_key_exists array=$elements key='usf_name_intern'}}
                {include 'sys-template-parts/form.input.tpl' data=$elements['usf_name_intern']}
            {/if}
            {if $systemField == 1}
                {include 'sys-template-parts/form.input.tpl' data=$elements['usf_cat_id']}
            {else}
                {include 'sys-template-parts/form.select.tpl' data=$elements['usf_cat_id']}
            {/if}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PROPERTIES')}</div>
        <div class="card-body">
            {if $systemField == 1}
                {include 'sys-template-parts/form.input.tpl' data=$elements['usf_type']}
            {else}
                {include 'sys-template-parts/form.select.tpl' data=$elements['usf_type']}
            {/if}
            {include 'sys-template-parts/form.option-editor.tpl' data=$elements['ufo_usf_options']}
            {if $fieldNameIntern == 'LAST_NAME' || $fieldNameIntern == 'FIRST_NAME'}
                {include 'sys-template-parts/form.input.tpl' data=$elements['usf_required_input']}
            {else}
                {include 'sys-template-parts/form.select.tpl' data=$elements['usf_required_input']}
            {/if}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['usf_hidden']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['usf_disabled']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['usf_registration']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['usf_default_value']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['usf_regex']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['usf_icon']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['usf_url']}
        </div>
    </div>
    <div class="card admidio-field-group admidio-panel-editor">
        <div class="card-body">
            {include 'sys-template-parts/form.editor.tpl' data=$elements['usf_description']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
