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
            {include 'sys-template-parts/form.input.tpl' data=$elements['ocl_client_secret']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['ocl_redirect_uri']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SECURITY')}</div>
        <div class="card-body">

            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['ocl_require_pkce']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['ocl_allow_refresh_token']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_SSO_USERDATA_ACCESS')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['ocl_userid_field']}

            <div class="admidio-form-group admidio-form-custom-content row mb-3">
                <label class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_SSO_ATTRIBUTES')}
                </label>
                <div class="col-sm-9">
                    <div class="table-responsive">
                        <table class="table table-condensed" id="oidc_fields_table">
                            <thead>
                            <tr class="nosort">
                                <th style="width: 50%;">{$l10n->get('SYS_PROFILE_FIELD')}</th>
                                <th style="width: 43%;">{$l10n->get('SYS_SSO_ATTRIBUTE')}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="oidc_fields_tbody"></tbody>
                            <tfoot>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <input id="{$elements['oidc_fields_all_other'].id}" name="{$elements['oidc_fields_all_other'].id}" class="form-check-input focus-ring " type="checkbox" value="1" 
                                    {foreach $elements['oidc_fields_all_other'].attributes as $itemvar}
                                        {$itemvar@key}="{$itemvar}"
                                    {/foreach} >
                                    <label class="form-check-label fw-normal" for="oidc_fields_all_other"> {$l10n->get('SYS_SSO_ATTRIBUTES_ALLOTHER')}</label>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <a class="icon-text-link" href="javascript:addColumn_oidc_fields()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_FIELD')}</a>
                                    <!--a class="icon-text-link" href="javascript:addAll_oidc_fields()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ALL_FIELDS')}</a-->
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <div class="form-text">{$l10n->get('SYS_SSO_ATTRIBUTES_DESC')}</div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
{*            <div class="admidio-form-group admidio-form-custom-content row mb-3">
                <label class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_SSO_OIDC_ROLES')}
                </label>
                <div class="col-sm-9">
                    <div class="table-responsive">
                        <table class="table table-condensed" id="oidc_roles_table">
                            <thead>
                            <tr class="nosort">
                                <th style="width: 50%;">{$l10n->get('SYS_ROLE')}</th>
                                <th style="width: 43%;">{$l10n->get('SYS_SSO_OIDC_ROLE')}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="oidc_roles_tbody"></tbody>
                            <tfoot>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <input id="{$elements['oidc_roles_all_other'].id}" name="{$elements['oidc_roles_all_other'].id}" class="form-check-input focus-ring " type="checkbox" value="1" 
                                    {foreach $elements['oidc_roles_all_other'].attributes as $itemvar}
                                        {$itemvar@key}="{$itemvar}"
                                    {/foreach} >
                                    <label class="form-check-label fw-normal" for="oidc_roles_all_other"> {$l10n->get('SYS_SSO_OIDC_ROLES_ALLOTHER')}</label>
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <a class="icon-text-link" href="javascript:addColumn_oidc_roles()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ROLE')}</a>
                                    <!--a class="icon-text-link" href="javascript:addAll_oidc_roles()"><i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ALL_ROLES')}</a-->
                                </td>
                            </tr>
                            <tr id="table_row_button nosort">
                                <td colspan="3">
                                    <div class="form-text">{$l10n->get('SYS_SSO_OIDC_ROLES_DESC')}</div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
*}
            {include 'sys-template-parts/form.select.tpl' data=$elements['oidc_roles_access']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
