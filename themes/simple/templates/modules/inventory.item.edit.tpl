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
            {if {array_key_exists array=$elements key='dummy'}}
                {include 'sys-template-parts/form.input.tpl' data=$elements['dummy']}  {* Dummy *}
            {/if}
            {include 'sys-template-parts/form.select.tpl' data=$elements['inf-2']}  {* Kategorie *}
            {include 'sys-template-parts/form.select.tpl' data=$elements['inf-3']}  {* Verwalter *}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['inf-4']}  {* Im Inventar *}
            {include 'sys-template-parts/form.select.tpl' data=$elements['inf-5']}  {* letzter Empfänger *}
            {include 'sys-template-parts/form.input.tpl' data=$elements['inf-5-hidden']}  {* letzter Empfänger versteckt *}
            {include 'sys-template-parts/form.input.tpl' data=$elements['inf-6']}  {* Ausgeliehen am *}
            {include 'sys-template-parts/form.input.tpl' data=$elements['inf-7']}  {* zurückerhalten am *}
        </div>
    </div>
    {if {array_key_exists array=$elements key='item_copy_number'}}
        <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('PLG_INVENTORY_MANAGER_COPY_PREFERENCES')}</div>
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
