<div class="table-responsive">
    <table id="adm_role_permissions_table" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th>{$l10n->get('SYS_CATEGORY')}</th>
                <th>{$l10n->get('SYS_GROUPS_ROLES')}</th>
                <th>{$l10n->get('SYS_PERMISSIONS')}</th>
                <th>{$l10n->get('SYS_SEND_MAILS')}</th>
                <th>{$l10n->get('SYS_VIEW_ROLE_MEMBERSHIPS')}</th>
                <th>{$l10n->get('SYS_VIEW_PROFILES_OF_ROLE_MEMBERS')}</th>
                <th>{$l10n->get('SYS_LEADER')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            {foreach $list as $row}
                <tr id="role_{$row.roleUUID}">
                    <td>{$row.category}</td>
                    <td><a href="{$row.roleUrl}">{$row.role}</a></td>
                    <td>
                        {foreach $row.roleRights as $roleRight}
                            <i class="admidio-icon-chain {$roleRight.icon}" data-bs-toggle="tooltip" title="{$roleRight.title}"></i>
                        {/foreach}
                    </td>
                    <td>{$row.emailToThisRole}</td>
                    <td>{$row.viewMembership}</td>
                    <td>{$row.viewMembersProfiles}</td>
                    <td>{$row.roleLeaderRights}</td>
                    <td class="text-end">
                        {foreach $row.actions as $actionItem}
                            <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                                data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                    {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                        {/foreach}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
