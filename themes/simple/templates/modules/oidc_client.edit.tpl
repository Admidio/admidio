<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_NAME_PROPERTIES')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['ocl_client_name']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['ocl_client_id']}
            {include 'sys-template-parts/form.custom-content.tpl' data=$elements['ocl_client_secret']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['ocl_redirect_uri']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_USERDATA_ACCESS')}</div>
        <div class="card-body">
        {include 'sys-template-parts/form.select.tpl' data=$elements['ocl_userid_field']}
        {include 'sys-template-parts/form.select.tpl' data=$elements['ocl_scope']}

            <div class="admidio-form-group admidio-form-custom-content row mb-3">
                <label class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_SSO_OIDC_ATTRIBUTES')}
                </label>
                <div class="col-sm-9">
                    <div class="table-responsive">
                        <table class="table table-condensed" id="fieldsmap_table">
                            <thead>
                            <tr class="nosort">
                                <th style="width: 50%;">{$l10n->get('SYS_PROFILE_FIELD')}</th>
                                <th style="width: 43%;">{$l10n->get('SYS_SSO_OIDC_CLAIM')}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="fieldsmap_tbody"></tbody>
                            <tfoot>
                            <tr id="table_row_button nosort">
                            {assign var='data' value=$elements['sso_fields_no_other']}
                                <td colspan="3">
                                    <input id="{$data.id}" name="{$data.id}" class="form-check-input focus-ring " type="checkbox" value="1" 
                                    {foreach $data.attributes as $itemvar}
                                        {$itemvar@key}="{$itemvar}"
                                    {/foreach} >
                                    <label class="form-check-label fw-normal" for="{$data.id}"> {$data.label}</label>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <a class="icon-text-link" href="javascript:addColumn_fieldsmap()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_FIELD')}</a>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <div class="form-text">{$l10n->get('SYS_SSO_OIDC_ATTRIBUTES_DESC')}</div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="admidio-form-group admidio-form-custom-content row mb-3">
                <label class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_SSO_ROLESMAP')}
                </label>
                <div class="col-sm-9">
                    <div class="table-responsive">
                        <table class="table table-condensed" id="rolesmap_table">
                            <thead>
                            <tr class="nosort">
                                <th style="width: 50%;">{$l10n->get('SYS_ROLE')}</th>
                                <th style="width: 43%;">{$l10n->get('SYS_SSO_OIDC_ROLE')}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="rolesmap_tbody"></tbody>
                            <tfoot>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <input id="{$elements['sso_roles_all_other'].id}" name="{$elements['sso_roles_all_other'].id}" class="form-check-input focus-ring " type="checkbox" value="1" 
                                    {foreach $elements['sso_roles_all_other'].attributes as $itemvar}
                                        {$itemvar@key}="{$itemvar}"
                                    {/foreach} >
                                    <label class="form-check-label fw-normal" for="sso_roles_all_other"> {$l10n->get('SYS_SSO_ROLES_ALLOTHER')}</label>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <a class="icon-text-link" href="javascript:addColumn_rolesmap()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ROLE')}</a>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <div class="form-text">{$l10n->get('SYS_SSO_ROLESMAP_DESC')}</div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {include 'sys-template-parts/form.select.tpl' data=$elements['sso_roles_access']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
