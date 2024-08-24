<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_USER_RELATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['urt_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['urt_name_male']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['urt_name_female']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['urt_edit_user']}
            {include 'sys-template-parts/form.radio.tpl' data=$elements['relation_type']}
        </div>
    </div>
    <div id="gb_opposite_relationship" class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_OPPOSITE_RELATIONSHIP')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['urt_name_inverse']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['urt_name_male_inverse']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['urt_name_female_inverse']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['urt_edit_user_inverse']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
