<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_NAME')} &amp; {$l10n->get('SYS_PROPERTIES')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['room_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['room_capacity']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['room_overhang']}
        </div>
    </div>
    <div class="card admidio-field-group admidio-panel-editor">
        <div class="card-header">{$l10n->get('SYS_DESCRIPTION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.editor.tpl' data=$elements['room_description']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
