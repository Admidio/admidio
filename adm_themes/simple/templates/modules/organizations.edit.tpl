<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_shortname']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_longname']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_homepage']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_email_administrator']}
    {if {array_key_exists array=$elements key='org_org_id_parent'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['org_org_id_parent']}
    {/if}
    {if {array_key_exists array=$elements key='org_show_org_select'}}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['org_show_org_select']}
    {/if}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>

<div class="card admidio-field-group">
    <div class="card-header">{$l10n->get('SYS_SUBORDINATE_ORGANIZATIONS')}</div>
    <div class="card-body">
        <p class="lead">{$l10n->get('ORG_ADD_ORGANIZATION_DESC')}</p>
        <p><a class="btn btn-primary" href="{$urlAdmidio}/adm_program/modules/preferences/preferences.php?mode=new_org_dialog"><i class="bi bi-plus-circle-fill"></i>{$l10n->get('SYS_ADD_ORGANIZATION')}</a></p>

        {if count($organizationsList) > 0}
            <table id="admOrganizationsTable" class="table table-hover" width="100%" style="width: 100%;">
                <thead>
                <tr>
                    <th>{$l10n->get('SYS_NAME_ABBREVIATION')}</th>
                    <th>{$l10n->get('SYS_NAME')}</th>
                    <th>{$l10n->get('SYS_WEBSITE')}</th>
                    <th>{$l10n->get('SYS_EMAIL_ADMINISTRATOR')}</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                {foreach $organizationsList as $row}
                    <tr id="row_{$row.org_uuid}">
                        <td>{$row.org_shortname}</td>
                        <td>{$row.org_longname}</td>
                        <td>{$row.org_homepage}</td>
                        <td>{$row.org_email_administrator}</td>
                        <td class="text-right">
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        {/if}
    </div>
</div>
