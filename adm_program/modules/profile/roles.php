<?php
/**
 ***********************************************************************************************
 * Show a list with all roles where the user can assign or remove membership
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_uuid : UUID of the user whose roles should be edited
 * accept_registration : If set to true, another forward url to role assignment will be set.
 * new_user  : If set to true, edit roles of a new user and set other forward url
 * inline    : false - wird als eigene Seite angezeigt
 *             true  - nur "body" HTML Code
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'bool');
    $getInline = admFuncVariableIsValid($_GET, 'inline', 'bool');
    $getAcceptRegistration = admFuncVariableIsValid($_GET, 'accept_registration', 'bool');

    $html = '';
    $setRoleId = 0;

    // if user is allowed to assign at least one role then allow access
    if (!$gCurrentUser->assignRoles()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    // set headline of the script
    $headline = $gL10n->get('SYS_ROLE_ASSIGNMENT_FOR', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));

    if (!$getInline) {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }
    // check if a special role should be set
    if (isset($_SESSION['set_rol_id'])) {
        $setRoleId = (int)$_SESSION['set_rol_id'];
        $role = new Role($gDb, $setRoleId);
        $role->startMembership($user->getValue('usr_id'));
        unset($_SESSION['set_rol_id']);
    }

    $page = null;

    $javascript = '
    // if checkbox of role is clicked then change membership
    $("#role_assignment_table input[type=checkbox]").click(function() {
        var checkbox = $(this);
        var roleUuid = $(this).data("role");

        var roleChecked = $("input[type=checkbox]#role-" + roleUuid).prop("checked");
        var leaderChecked = $("input[type=checkbox]#leader-" + roleUuid).prop("checked");

        // If the group leader checkbox is set, the role checkbox must also be set
        if (checkbox.data("type") === "leader" && leaderChecked) {
            $("input[type=checkbox]#role-" + roleUuid).prop("checked", true);
            roleChecked = true;
        }

        // When removing the membership also ends the leader assignment
        if (checkbox.data("type") === "membership" && !roleChecked) {
            $("input[type=checkbox]#leader-" + roleUuid).prop("checked", false);
            leaderChecked = false;
        }

        // change data in database
        $.post(gRootPath + "/adm_program/modules/groups-roles/members_assignment.php?mode=assign&role_uuid=" + roleUuid + "&user_uuid=' . $getUserUuid . '",
            "memberFlag=" + roleChecked + "&leaderFlag=" + leaderChecked + "&adm_csrf_token=' . $gCurrentSession->getCsrfToken() . '",
            function(data) {
                // check if error occurs
                if (data === "success") {
                    $("#admidio-profile-roles-alert").fadeOut();
                } else {
                    // reset checkbox status
                    if (checkbox.prop("checked")) {
                        checkbox.prop("checked", false);
                        if (checkbox.data("type") === "leader") {
                            $("input[type=checkbox]#role-" + roleUuid).prop("checked", false);
                        }
                    } else {
                        checkbox.prop("checked", true);
                    }

                    $("#admidio-profile-roles-alert").fadeIn();
                    $("#admidio-profile-roles-alert").html("<i class=\"bi bi-exclamation-circle-fill\"></i>" + data);
                    return false;
                }
                return true;
            }
        );
    });';

    if ($getInline) {
        header('Content-type: text/html; charset=utf-8');

        $html .= '<script type="text/javascript">
        $(function() {
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
        });
        ' . $javascript . '
        </script>

        <div class="modal-header">
            <h3 class="modal-title">' . $headline . '</h3>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">';
    } else {
        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-profile-roles', $headline);
        $page->addJavascript($javascript, true);
        $messageId = '';

        if ($getAcceptRegistration) {
            $messageId = $gL10n->get('SYS_ASSIGN_REGISTRATION_SUCCESSFUL');
            $nextUrl = $gNavigation->getStackEntryUrl(0);
        } elseif ($getNewUser) {
            $nextUrl = $gNavigation->getStackEntryUrl($gNavigation->count() - 3);
        } else {
            $nextUrl = $gNavigation->getPreviousUrl();
        }
        $page->addJavascript('
        $("#btn-next").click(function() {
            var oneMembershipSet = false;
            var messageOk = "' . $messageId . '";
            $("#role_assignment_table input[type=checkbox]").each(function(){
                if($(this).data("type") === "membership" && $(this).prop("checked")) {
                    oneMembershipSet = true;
                }
            });

            if(oneMembershipSet) {
                if(messageOk == "") {
                    window.location.href = "' . $nextUrl . '";
                } else {
                    $("#btn-next").prop("disabled", true) ;
                    $("#admidio-profile-roles-alert").attr("class", "alert alert-success form-alert");
                    $("#admidio-profile-roles-alert").fadeIn();
                    $("#admidio-profile-roles-alert").html("<i class=\"bi bi-check-lg\"></i>" + messageOk);
                    setTimeout(function() {
                        window.location.href = "' . $nextUrl . '";
                    }, 3000);
                 }
            } else {
                $("#admidio-profile-roles-alert").fadeIn();
                $("#admidio-profile-roles-alert").html("<i class=\"bi bi-exclamation-circle-fill\"></i>' . $gL10n->get('SYS_ASSIGN_ROLE_TO_USER') . '");
            }
        });', true);
    }

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
        // User with role rights may assign ALL roles
        $sql = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_uuid, rol_leader_rights, mem_rol_id, mem_usr_id, mem_leader
              FROM ' . TBL_ROLES . '
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
         LEFT JOIN ' . TBL_MEMBERS . '
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
        // Leader may only assign roles for which he is also the leader
        $sql = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_uuid, rol_leader_rights,
                   mgl.mem_rol_id AS mem_rol_id, mgl.mem_usr_id AS mem_usr_id, mgl.mem_leader AS mem_leader
              FROM ' . TBL_MEMBERS . ' AS bm
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = bm.mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
         LEFT JOIN ' . TBL_MEMBERS . ' AS mgl
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
            Role::ROLE_LEADER_MEMBERS_ASSIGN,
            Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT,
            $gCurrentOrgId
        );
    }
    $statement = $gDb->queryPrepared($sql, $queryParams);
    $category = null;
    $role = new Role($gDb);

    while ($row = $statement->fetch()) {
        $columnValues = array();
        $memberChecked = '';
        $memberDisabled = '';
        $leaderChecked = '';
        $leaderDisabled = '';
        $role->setArray($row);

        // if user is assigned to this role
        // or if user is created in contacts.php of list module
        if ($row['mem_usr_id'] > 0 || (!$getAcceptRegistration && (int)$role->getValue('rol_id') === $setRoleId)) {
            $memberChecked = ' checked="checked" ';
        }

        // if role is administrator than only administrator can add new user,
        // but don't change their own membership, because there must be at least one administrator
        if ($role->getValue('rol_administrator') == 1
            && (!$gCurrentUser->isAdministrator()
                || ($gCurrentUser->isAdministrator() && (int)$user->getValue('usr_id') === $gCurrentUserId))) {
            $memberDisabled = ' disabled="disabled" ';
        }

        // if user is flagged as leader than check the checkbox ;)
        if ($row['mem_leader'] > 0) {
            $leaderChecked = ' checked="checked" ';
        }

        // the leader of administrator role can only be set by an administrator
        if ($role->getValue('rol_administrator') == 1 && !$gCurrentUser->isAdministrator()) {
            $leaderDisabled = ' disabled="disabled" ';
        }

        $columnValues = array(
            '<input type="checkbox" id="role-' . $role->getValue('rol_uuid') . '" name="role-' . $role->getValue('rol_uuid') . '"
            data-role="' . $role->getValue('rol_uuid') . '" data-type="membership" ' .
            $memberChecked . $memberDisabled . ' value="1" />',
            '<label for="role-' . (int)$role->getValue('rol_id') . '">' . $role->getValue('rol_name') . '</label>',
            $role->getValue('rol_description')
        );

        // if new category than display a category header
        if ($category !== (int)$role->getValue('cat_id')) {
            $blockId = 'adm_category_' . (int)$role->getValue('cat_id');

            $table->addTableBody();
            $table->addRow('', array('class' => 'admidio-group-heading', 'id' => 'group_' . $blockId));
            $table->addColumn();
            $table->addAttribute('colspan', '4', 'td');
            $table->addData('<a id="caret_' . $blockId . '" class="admidio-icon-link admidio-open-close-caret" data-target="' . $blockId . '"><i class="bi bi-caret-down-fill"></i></a>' . $role->getValue('cat_name'));
            $table->addTableBody('id', $blockId);

            $category = (int)$role->getValue('cat_id');
        }

        $leaderRights = '<input type="checkbox" id="leader-' . $role->getValue('rol_uuid') . '" name="leader-' . $role->getValue('rol_uuid') . '"
                       data-role="' . $role->getValue('rol_uuid') . '" data-type="leader" ' .
            $leaderChecked . $leaderDisabled . ' value="1" />';

        // show icon that leaders have no additional rights
        if ((int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_NO_RIGHTS) {
            $leaderRights .= '<i class="bi bi-info-circle-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_LEADER_NO_ADDITIONAL_RIGHTS') . '"></i>
                          <i class="bi bi-trash invisible admidio-icon"></i>';
        }

        // show icon with edit user right if leader has this right
        if ((int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_EDIT
            || (int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
            $leaderRights .= '<i class="bi bi-pencil-fill admidio-icon" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_LEADER_EDIT_MEMBERS') . '"></i>';
        }

        // show icon with assign role right if leader has this right
        if ((int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_ASSIGN
            || (int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
            $leaderRights .= '<i class="bi bi-people-fill admidio-icon" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_LEADER_ASSIGN_MEMBERS') . '"></i>';
        }

        // show dummy icon if leader has not all rights
        if ((int)$role->getValue('rol_leader_rights') !== Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
            $leaderRights .= '<i class="bi bi-trash invisible admidio-icon"></i>';
        }
        $columnValues[] = $leaderRights;

        $table->addRowByArray($columnValues);
    }
    $html .= $table->show() . '<div id="admidio-profile-roles-alert" class="alert alert-danger form-alert" style="display: none;">&nbsp;</div>';

    if ($getInline) {
        echo $html . '</div>';
    } else {
        $html .= '<button class="btn-primary btn admidio-margin-bottom" id="btn-next" type="submit"><i class="bi bi-check-lg"></i>' . $gL10n->get('SYS_NEXT') . '</button>';
        $page->addHtml($html);
        $page->show();
    }
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
