<table class="table table-hover">
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
    {foreach $list as $row}
        <tr>
            <td>{$row.category}</td>
            <td><a href="{$row.roleUrl}">{$row.role}</a></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    {/foreach}
</table>
