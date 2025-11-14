<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <p class="lead">{$l10n->get('SYS_INVENTORY_IMPORT_ASSIGN_FIELDS_DESC')}</p>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['first_row'] formType="vertical"}

    <div class="alert alert-warning alert-small" id="admidio-import-unused">
        <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('SYS_INVENTORY_IMPORT_UNUSED_HEAD')}
        <div id="admidio-import-unused-fields">-</div>
    </div>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {foreach $elements as $key => $itemField}
        {if {array_key_exists array=$itemField key="category"}}
            {if $itemField.category != $lastCategory}
                {if $lastCategory != ''}
                    </div></div>
                {/if}
                {$lastCategory = {$itemField.category}}
                <div class="card admidio-field-group">
                    <div class="card-header">{$itemField.category}</div>
                    <div class="card-body">
            {/if}
            {include 'sys-template-parts/form.select.tpl' data=$itemField}
        {/if}
    {/foreach}
    </div></div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_forward']}
</form>
