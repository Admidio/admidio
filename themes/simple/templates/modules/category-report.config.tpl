<p>{$l10n->get('SYS_CONFIGURATIONS_HEADER')}</p>
<hr />

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    {foreach $categoryReports as $categoryReport}
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_CONFIGURATION')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.input.tpl' data=$elements[$categoryReport.name]}
                <div class="admidio-form-group admidio-form-custom-content row mb-3">
                    <label class="col-sm-3 col-form-label">
                        {$l10n->get('SYS_COLUMN_SELECTION')}
                    </label>
                    <div class="col-sm-9">
                        <div class="table-responsive">
                            <table class="table table-condensed" id="mylist_fields_table">
                                <thead>
                                <tr>
                                    <th style="width: 20%;">{$l10n->get('SYS_ABR_NO')}</th>
                                    <th style="width: 37%;">{$l10n->get('SYS_CONTENT')}</th>
                                </tr>
                                </thead>
                                <tbody id="mylist_fields_tbody{$categoryReport.key}">
                                <tr id="table_row_button">
                                    <td colspan="2">
                                        <a class="icon-text-link" href="javascript:addColumn{$categoryReport.key}()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_COLUMN')}</a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text">{$l10n->get('SYS_COLUMN_SELECTION_DESC')}</div>
                    </div>
                </div>
                {include 'sys-template-parts/form.select.tpl' data=$elements[$categoryReport.selection_role]}
                {include 'sys-template-parts/form.select.tpl' data=$elements[$categoryReport.selection_cat]}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements[$categoryReport.number_col]}
                {include 'sys-template-parts/form.input.tpl' data=$elements[$categoryReport.id]}
                {include 'sys-template-parts/form.input.tpl' data=$elements[$categoryReport.default_conf]}

                {if isset($categoryReport.urlConfigCopy)}
                    <a id="copy_config" class="icon-text-lin offset-sm-3" href="{$categoryReport.urlConfigCopy}">
                        <i class="bi bi-copy"></i> {$l10n->get('SYS_COPY_CONFIGURATION')}</a>
                {/if}
                {if isset($categoryReport.urlConfigDelete)}
                    &nbsp;&nbsp;&nbsp;&nbsp;<a id="delete_config" class="icon-text-link offset-sm-3" href="{$categoryReport.urlConfigDelete}">
                    <i class="bi bi-trash"></i> {$l10n->get('SYS_DELETE_CONFIGURATION')}</a>
                {/if}
            </div>
        </div>
    {/foreach}

    <hr />
    <a id="add_config" class="icon-text-link" href="{$urlConfigNew}">
        <i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ANOTHER_CONFIG')}
    </a>
    <div class="alert alert-warning alert-small" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('ORG_NOT_SAVED_SETTINGS_LOST')}
    </div>

    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_configurations']}
</form>
