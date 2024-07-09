<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <p class="lead">{$l10n->get('SYS_ASSIGN_FIELDS_DESC')}</p>
    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['first_row'] formType="vertical"}

    <div class="alert alert-warning alert-small" id="admidio-import-unused">
        <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('SYS_IMPORT_UNUSED_HEAD')}
        <div id="admidio-import-unused-fields">-</div>
    </div>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {$lastCategory = ''}

    {foreach $elements as $key => $profileField}
        {if $key !== 'first_row' &&  $key !== 'admidio-csrf-token' &&  $key !== 'btn_forward'}
            {if $profileField.category != $lastCategory}
                {if $lastCategory != ''}
                    </div></div>
                {/if}
                {$lastCategory = {$profileField.category}}
                <div class="card admidio-field-group">
                    <div class="card-header">{$profileField.category}</div>
                    <div class="card-body">
            {/if}
            {include 'sys-template-parts/form.select.tpl' data=$profileField}
        {/if}
    {/foreach}
    </div></div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_forward']}
</form>
