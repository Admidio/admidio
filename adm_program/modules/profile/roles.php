<?php
/**
 ***********************************************************************************************
 * Show a list with all roles where the user can assign or remove membership
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_uuid : UUID of the user whose roles should be edited
 * new_user  : 0 - (Default) Edit roles of an existing user
 *             1 - Edit roles of a new user
 *             2 - (not relevant)
 *             3 - Edit roles of a registration
 * inline    : false - wird als eigene Seite angezeigt
 *             true  - nur "body" HTML Code
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
$getNewUser  = admFuncVariableIsValid($_GET, 'new_user', 'int');
$getInline   = admFuncVariableIsValid($_GET, 'inline', 'bool');

$html = '';

// if user is allowed to assign at least one role then allow access
if (!$gCurrentUser->assignRoles()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

// set headline of the script
$headline = $gL10n->get('SYS_ROLE_ASSIGNMENT_FOR', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));

if (!$getInline) {
    $gNavigation->addUrl(CURRENT_URL, $headline);
}
// Testen ob Feste Rolle gesetzt ist
if (isset($_SESSION['set_rol_id'])) {
    $setRoleId = $_SESSION['set_rol_id'];
    unset($_SESSION['set_rol_id']);
} else {
    $setRoleId = null;
}

$page = null;

if ($getInline) {
    header('Content-type: text/html; charset=utf-8');

    $html .= '<script type="text/javascript">
        $(function() {
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this).attr("id"));
            });

            $("#roles_assignment_form").submit(function(event) {
                var action = $(this).attr("action");
                var rolesFormAlert = $("#roles_assignment_form .form-alert");
                rolesFormAlert.hide();

                // disable default form submit
                event.preventDefault();

                $.post({
                    url: action,
                    data: $(this).serialize(),
                    success: function(data) {
                        if (data === "success") {
                            rolesFormAlert.attr("class", "alert alert-success form-alert");
                            rolesFormAlert.html("<i class=\"fas fa-check\"></i><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                            rolesFormAlert.fadeIn("slow");
                            setTimeout(function() {
                                $("#admidio-modal").modal("hide");
                            }, 2000);

                            profileJS.reloadRoleMemberships();
                            profileJS.reloadFormerRoleMemberships();
                            profileJS.reloadFutureRoleMemberships();
                        } else {
                            rolesFormAlert.attr("class", "alert alert-danger form-alert");
                            rolesFormAlert.fadeIn();
                            rolesFormAlert.html("<i class=\"fas fa-exclamation-circle\"></i>" + data);
                        }
                    }
                });
            });
        });
    </script>

    <div class="modal-header">
        <h3 class="modal-title">'.$headline.'</h3>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body">';
} else {
    // create html page object
    $page = new HtmlPage('admidio-profile-roles', $headline);
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.js');

    $page->addJavascript('var profileJS = new ProfileJS(gRootPath);');
}

// show headline of module
$html .= '<form id="roles_assignment_form" action="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/roles_save.php', array('user_uuid' => $getUserUuid, 'new_user' => $getNewUser, 'inline' => $getInline)).'" method="post">
    <input type="text" name="admidio-csrf-token" id="admidio-csrf-token" value="' . $gCurrentSession->getCsrfToken() . '" class="form-control invisible" hidden="hidden">';

// Create table
$table = new HtmlTable('role_assignment_table');
$columnHeading = array(
    '&nbsp;',
    $gL10n->get('SYS_ROLE'),
    $gL10n->get('SYS_DESCRIPTION'),
    $gL10n->get('SYS_LEADER')
);
$table->addRowHeadingByArray($columnHeading);
$table->setColumnAlignByArray(array('center', 'left', 'left', 'left'));
$table->setColumnsWidth(array('10%', '30%', '45%', '15%'));

if ($gCurrentUser->manageRoles()) {
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    $sql = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_leader_rights, mem_rol_id, mem_usr_id, mem_leader
              FROM '.TBL_ROLES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.'
                ON rol_id      = mem_rol_id
               AND mem_usr_id  = ? -- $user->getValue(\'usr_id\')
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end     > ? -- DATE_NOW
             WHERE rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, cat_id, rol_name';
    $queryParams = array(
        $user->getValue('usr_id'),
        DATE_NOW,
        DATE_NOW,
        $gCurrentOrgId
    );
} else {
    // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
    $sql = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_leader_rights,
                   mgl.mem_rol_id AS mem_rol_id, mgl.mem_usr_id AS mem_usr_id, mgl.mem_leader AS mem_leader
              FROM '.TBL_MEMBERS.' AS bm
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = bm.mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.' AS mgl
                ON rol_id         = mgl.mem_rol_id
               AND mgl.mem_usr_id = ? -- $user->getValue(\'usr_id\')
               AND mgl.mem_begin <= ? -- DATE_NOW
               AND mgl.mem_end    > ? -- DATE_NOW
             WHERE bm.mem_usr_id  = ? -- $gCurrentUserId
               AND bm.mem_begin  <= ? -- DATE_NOW
               AND bm.mem_end     > ? -- DATE_NOW
               AND bm.mem_leader  = true
               AND rol_leader_rights IN (?,?) -- ROLE_LEADER_MEMBERS_ASSIGN,ROLE_LEADER_MEMBERS_ASSIGN_EDIT
               AND rol_valid      = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id  = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, cat_id, rol_name';
    $queryParams = array(
        $user->getValue('usr_id'),
        DATE_NOW,
        DATE_NOW,
        $gCurrentUserId,
        DATE_NOW,
        DATE_NOW,
        ROLE_LEADER_MEMBERS_ASSIGN,
        ROLE_LEADER_MEMBERS_ASSIGN_EDIT,
        $gCurrentOrgId
    );
}
$statement = $gDb->queryPrepared($sql, $queryParams);
$category  = null;
$role      = new TableRoles($gDb);

while ($row = $statement->fetch()) {
    $columnValues   = array();
    $memberChecked  = '';
    $memberDisabled = '';
    $leaderChecked  = '';
    $leaderDisabled = '';
    $role->setArray($row);

    // if user is assigned to this role
    // or if user is created in members.php of list module
    if ($row['mem_usr_id'] > 0 || ($getNewUser === 1 && (int) $role->getValue('rol_id') == $setRoleId)) {
        $memberChecked = ' checked="checked" ';
    }

    // if role is administrator than only administrator can add new user,
    // but don't change their own membership, because there must be at least one administrator
    if ($role->getValue('rol_administrator') == 1
    && (!$gCurrentUser->isAdministrator()
    || ($gCurrentUser->isAdministrator() && (int) $user->getValue('usr_id') === $gCurrentUserId))) {
        $memberDisabled = ' disabled="disabled" ';
    }

    // if user is flagged as leader than check the ckeckbox ;)
    if ($row['mem_leader'] > 0) {
        $leaderChecked = ' checked="checked" ';
    }

    // the leader of administrator role can only be set by a administrator
    if ($role->getValue('rol_administrator') == 1 && !$gCurrentUser->isAdministrator()) {
        $leaderDisabled = ' disabled="disabled" ';
    }

    $columnValues = array(
        '<input type="checkbox" id="role-'.(int) $role->getValue('rol_id').'" name="role-'.(int) $role->getValue('rol_id').'" '.
            $memberChecked.$memberDisabled.' onclick="profileJS.unMarkLeader(this);" value="1" />',
        '<label for="role-'.(int) $role->getValue('rol_id').'">'.$role->getValue('rol_name').'</label>',
        $role->getValue('rol_description')
    );

    // if new category than display a category header
    if ($category !== (int) $role->getValue('cat_id')) {
        $blockId = 'admCategory'.(int) $role->getValue('cat_id');

        $table->addTableBody();
        $table->addRow('', array('class' => 'admidio-group-heading', 'id' => 'group_'.$blockId));
        $table->addColumn();
        $table->addAttribute('colspan', '4', 'td');
        $table->addData('<a id="caret_'.$blockId.'" class="admidio-icon-link admidio-open-close-caret"><i class="fas fa-caret-down"></i></a>'.$role->getValue('cat_name'));
        $table->addTableBody('id', $blockId);

        $category = (int) $role->getValue('cat_id');
    }

    $leaderRights = '<input type="checkbox" id="leader-'.(int) $role->getValue('rol_id').'" name="leader-'.(int) $role->getValue('rol_id').'" '.
                       $leaderChecked.$leaderDisabled.' onclick="profileJS.markLeader(this);" value="1" />';

    // show icon that leaders have no additional rights
    if ((int) $role->getValue('rol_leader_rights') === ROLE_LEADER_NO_RIGHTS) {
        $leaderRights .= '<i class="fas fa-info-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_LEADER_NO_ADDITIONAL_RIGHTS').'"></i>
                          <i class="fas fa-trash invisible"></i>';
    }

    // show icon with edit user right if leader has this right
    if ((int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_EDIT
    || (int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
        $leaderRights .= '<i class="fas fa-user-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_LEADER_EDIT_MEMBERS').'"></i>';
    }

    // show icon with assign role right if leader has this right
    if ((int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_ASSIGN
    || (int) $role->getValue('rol_leader_rights') === ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
        $leaderRights .= '<i class="fas fa-user-tie" data-toggle="tooltip" title="'.$gL10n->get('SYS_LEADER_ASSIGN_MEMBERS').'"></i>';
    }

    // show dummy icon if leader has not all rights
    if ((int) $role->getValue('rol_leader_rights') !== ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
        $leaderRights .= '<i class="fas fa-trash invisible"></i>';
    }
    $columnValues[] = $leaderRights;

    $table->addRowByArray($columnValues);
}
$html .= $table->show();

$html .= '
    <button class="btn-primary btn" id="btn_save" type="submit"><i class="fas fa-check"></i>'.$gL10n->get('SYS_SAVE').'</button>
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>';

if ($getInline) {
    echo $html.'</div>';
} else {
    $page->addHtml($html);
    $page->show();
}
