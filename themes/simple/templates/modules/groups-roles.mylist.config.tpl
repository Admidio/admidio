<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_CONFIGURATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['sel_select_configuration']}
            {if {array_key_exists array=$elements key='cbx_global_configuration'}}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['cbx_global_configuration']}
            {/if}
            <p>{$l10n->get('SYS_ADD_COLUMNS_DESC')}</p>
            <div class="table-responsive">
                <table class="table table-condensed" id="mylist_fields_table">
                    <thead>
                    <tr>
                        <th style="width: 17%;">{$l10n->get('SYS_ABR_NO')}</th>
                        <th style="width: 35%;">{$l10n->get('SYS_CONTENT')}</th>
                        <th style="width: 17%;">{$l10n->get('SYS_ORDER')}</th>
                        <th style="width: 25%;">{$l10n->get('SYS_CONDITION')}
                            <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-class="modal-lg" data-href="{$urlConditionHelpText}">
                                <i class="bi bi-info-circle-fill admidio-info-icon"></i>
                            </a>
                        </th>
                        <th style="width: 6%;">&nbsp;</th>
                    </tr>
                    </thead>
                    <tbody id="mylist_fields_tbody">
                    </tbody>
                </table>
            </div>
            <div class="btn-group" role="group">
                {include 'sys-template-parts/form.button.tpl' data=$elements['btn_add_column']}
                {if {array_key_exists array=$elements key='adm_button_save_changes'}}
                    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_changes']}
                {/if}
                {if {array_key_exists array=$elements key='adm_button_save'}}
                    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
                {/if}
                {if {array_key_exists array=$elements key='btn_delete'}}
                    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_delete']}
                {/if}
                {if {array_key_exists array=$elements key='btn_copy'}}
                    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_copy']}
                {/if}
            </div>
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SELECT_MEMBERS')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['sel_roles']}
            {if {array_key_exists array=$elements key='sel_relation_types'}}
                {include 'sys-template-parts/form.select.tpl' data=$elements['sel_relation_types']}
            {/if}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_show_list']}
</form>
