<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_DESIGNATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['inf_name']}
            {if {array_key_exists array=$elements key='inf_name_intern'}}
                {include 'sys-template-parts/form.input.tpl' data=$elements['inf_name_intern']}
            {/if}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PROPERTIES')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['inf_type']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['inf_inf_uuid_connected']}
            {include 'sys-template-parts/form.option-editor.tpl' data=$elements['ifo_inf_options']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['inf_required_input']}
        </div>
    </div>
    <div class="card admidio-field-group admidio-panel-editor">
        <div class="card-body">
            {include 'sys-template-parts/form.editor.tpl' data=$elements['inf_description']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
