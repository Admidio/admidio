<?php
/**
 ***********************************************************************************************
 * Assign or remove members to role
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode        - html   : Default mode to show a html list with all users to add them to the role
 *               assign : Add membership of a specific user to the role.
 * role_uuid            : UUID of role to which members should be assigned or removed
 * user_uuid            : UUID of the user whose membership should be assigned or removed
 * filter_rol_uuid      : If set only users from this role will be shown in list.
 * mem_show_all - true  : (Default) Show active and inactive members of all organizations in database
 *                false : Show only active members of the current organization
 *****************************************************************************/

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'assign')));
    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid', array('requireValue' => true, 'directOutput' => true));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('directOutput' => true));
    $getFilterRoleUuid = admFuncVariableIsValid($_GET, 'filter_rol_uuid', 'uuid');
    $getMembersShowAll = admFuncVariableIsValid($_GET, 'mem_show_all', 'bool', array('defaultValue' => false));

    // create object of the committed role
    $role = new Role($gDb);
    $role->readDataByUuid($getRoleUuid);

    // check if user is allowed to assign members to this role
    if (!$role->allowedToAssignMembers($gCurrentUser)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getMode === 'assign') {
        // change membership of that user
        // this must be called as ajax request

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        $leadership = false;
        if (isset($_POST['leaderFlag']) && $_POST['leaderFlag'] === 'true') {
            $leadership = true;
        }

        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUuid);

        if ((isset($_POST['memberFlag']) && $_POST['memberFlag'] === 'true')
            || $leadership) {
            $role->startMembership($user->getValue('usr_id'), $leadership);
        } else {
            $role->stopMembership($user->getValue('usr_id'));
        }

        echo 'success';
        exit();
    } else {
        // show html list with all users and their membership to this role

        $javascriptCode = '';
        $headline = $gL10n->get('SYS_MEMBER_ASSIGNMENT') . ' - ' . $role->getValue('rol_name');
        $_SESSION['set_rol_id'] = $role->getValue('rol_id');

        if ($getMembersShowAll) {
            $getFilterRoleUuid = '';
        }

        if ($getFilterRoleUuid !== '') {
            $filterRole = new Role($gDb);
            $filterRole->readDataByUuid($getFilterRoleUuid);
            if (!$gCurrentUser->hasRightViewRole($filterRole->getValue('rol_id'))) {
                throw new Exception('SYS_NO_RIGHTS_VIEW_LIST');
            }
        }

        // add current url to navigation stack if last url was not the same page
        if (!str_contains($gNavigation->getUrl(), 'members_assignment.php')) {
            $gNavigation->addUrl(CURRENT_URL, $headline);
        }

        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-members-assignement', $headline);

        if ($getMembersShowAll) {
            $javascriptCode .= '$("#mem_show_all").prop("checked", true);';
        }

        $javascriptCode .= '
        $("#menu_item_members_assign_create_user").attr("href", "javascript:void(0);");
        $("#menu_item_members_assign_create_user").attr("data-href", "' . ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_new.php");
        $("#menu_item_members_assign_create_user").attr("class", "nav-link btn btn-secondary openPopup");

        // change mode of users that should be shown
        $("#filter_rol_uuid").change(function() {
            window.location.replace("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $getRoleUuid, 'mem_show_all' => 0)) . '&filter_rol_uuid=" + $("#filter_rol_uuid").val());
        });

        // change mode of users that should be shown
        $("#mem_show_all").click(function() {
            if ($("#mem_show_all").is(":checked")) {
                window.location.replace("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $getRoleUuid, 'mem_show_all' => 1)) . '");
            } else {
                window.location.replace("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $getRoleUuid, 'mem_show_all' => 0)) . '");
            }
        });

        // if checkbox of user is clicked then change membership
        $("#adm_assign_role_membership").on("click", "input[type=checkbox]", function() {
            var checkbox = $(this);
            var userUuid = $(this).data("user");

            var memberChecked = $("input[type=checkbox]#member-" + userUuid).prop("checked");
            var leaderChecked = $("input[type=checkbox]#leader-" + userUuid).prop("checked");

            // If the group leader checkbox is set, the member checkbox must also be set
            if (checkbox.data("type") === "leader" && leaderChecked) {
                $("input[type=checkbox]#member-" + userUuid).prop("checked", true);
                memberChecked = true;
            }

            // When removing the membership also ends the leader assignment
            if (checkbox.data("type") === "member" && !memberChecked) {
                $("input[type=checkbox]#leader-" + userUuid).prop("checked", false);
                leaderChecked = false;
            }

            // change data in database
            $.post(gRootPath + "/modules/groups-roles/members_assignment.php?mode=assign&role_uuid=' . $getRoleUuid . '&user_uuid=" + userUuid,
                "memberFlag=" + memberChecked + "&leaderFlag=" + leaderChecked + "&adm_csrf_token=' . $gCurrentSession->getCsrfToken() . '",
                function(data) {
                    // check if error occurs
                    if (data !== "success") {
                        // reset checkbox status
                        if (checkbox.prop("checked")) {
                            checkbox.prop("checked", false);
                            if (checkbox.data("type") === "leader") {
                                $("input[type=checkbox]#member-" + userUuid).prop("checked", false);
                            }
                        } else {
                            checkbox.prop("checked", true);
                        }

                        messageBox(data, "' . $gL10n->get('SYS_ERROR') . '", "error");
                        return false;
                    }
                    return true;
                }
            );
        });';

        $page->addJavascript($javascriptCode, true);

        if ($gCurrentUser->isAdministratorUsers()) {
            $page->addPageFunctionsMenuItem(
                'menu_item_members_assign_create_user',
                $gL10n->get('SYS_CREATE_MEMBER'),
                ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_new.php',
                'bi-plus-circle-fill'
            );
        }

        ChangelogService::displayHistoryButton($page, 'membership', 'members', true, array('uuid' => $getUserUuid, 'related_id' => $getRoleUuid));

        $allVisibleRoles = array_merge(array(0), $gCurrentUser->getRolesViewMemberships());
        $sqlData['query'] = 'SELECT rol_uuid, rol_name, cat_name
                           FROM ' . TBL_ROLES . '
                     INNER JOIN ' . TBL_CATEGORIES . '
                             ON cat_id = rol_cat_id
                          WHERE rol_valid   = true
                            AND rol_uuid IN (' . Database::getQmForValues($allVisibleRoles) . ')
                            AND cat_name_intern <> \'EVENTS\'
                       ORDER BY cat_sequence, rol_name';
        $sqlData['params'] = $allVisibleRoles;

        // create filter menu with elements for role
        $form = new FormPresenter(
            'adm_navbar_filter_form_roles',
            'sys-template-parts/form.filter.tpl',
            '',
            $page,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addSelectBoxFromSql(
            'filter_rol_uuid',
            $gL10n->get('SYS_ROLE'),
            $gDb,
            $sqlData,
            array('defaultValue' => $getFilterRoleUuid, 'firstEntry' => $gL10n->get('SYS_ALL'))
        );
        $form->addCheckbox('mem_show_all', $gL10n->get('SYS_SHOW_ALL'), false, array('helpTextId' => 'SYS_SHOW_ALL_DESC'));
        $form->addToHtmlPage();

        // create table object
        $table = new DataTables($page, 'adm_assign_role_membership');
        $table->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

        // create column header to assign role leaders
        $htmlLeaderText = '';

        // show icon that leaders have no additional rights
        if ((int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_NO_RIGHTS) {
            $htmlLeaderText .= $gL10n->get('SYS_LEADER_NO_ADDITIONAL_RIGHTS');
        }

        // show icon with edit user right if leader has this right
        if ((int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_EDIT
            || (int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
            $htmlLeaderText .= $gL10n->get('SYS_LEADER_EDIT_MEMBERS');
        }

        // show icon with assign role right if leader has this right
        if ((int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_ASSIGN
            || (int)$role->getValue('rol_leader_rights') === Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT) {
            $htmlLeaderText .= $gL10n->get('SYS_LEADER_ASSIGN_MEMBERS');
        }

        // create array with all column heading values
        $columnHeading = array(
            '<i class="bi bi-person-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname'))) . '"></i>',
            $gL10n->get('SYS_MEMBER'));
        $columnAlignment = array('left', 'center');

        if ($gProfileFields->isVisible('LAST_NAME', $gCurrentUser->isAdministratorUsers())) {
            $columnHeading[] = $gL10n->get('SYS_LASTNAME');
            $columnAlignment[] = 'left';
        }
        if ($gProfileFields->isVisible('FIRST_NAME', $gCurrentUser->isAdministratorUsers())) {
            $columnHeading[] = $gL10n->get('SYS_FIRSTNAME');
            $columnAlignment[] = 'left';
        }
        if ($gProfileFields->isVisible('STREET', $gCurrentUser->isAdministratorUsers())
            || $gProfileFields->isVisible('POSTCODE', $gCurrentUser->isAdministratorUsers())
            || $gProfileFields->isVisible('CITY', $gCurrentUser->isAdministratorUsers())
            || $gProfileFields->isVisible('COUNTRY', $gCurrentUser->isAdministratorUsers())) {
            $columnHeading[] = $gL10n->get('SYS_ADDRESS');
            $columnAlignment[] = 'left';
        }
        if ($gProfileFields->isVisible('BIRTHDAY', $gCurrentUser->isAdministratorUsers())) {
            $columnHeading[] = $gL10n->get('SYS_BIRTHDAY');
            $columnAlignment[] = 'left';
        }
        $columnHeading[] = $gL10n->get('SYS_LEADER') . FormPresenter::getHelpTextIcon($htmlLeaderText);
        $columnAlignment[] = 'center';

        $table->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment_data.php', array('role_uuid' => $getRoleUuid, 'filter_rol_uuid' => $getFilterRoleUuid, 'mem_show_all' => $getMembersShowAll)));
        $table->setColumnAlignByArray($columnAlignment);
        $table->setColumnsNotHideResponsive(array(1, count($columnHeading)));
        $table->createJavascript(0, count($columnHeading));

        $smarty = $page->createSmartyObject();
        $smarty->assign('columnAlign', $columnAlignment);
        $smarty->assign('headers', $columnHeading);

        // Fetch the HTML table from our Smarty template
        $htmlTable = $smarty->fetch('modules/groups-roles.members-assignment.list.tpl');

        $page->addHtml($htmlTable);
        $page->addHtml('<p>' . $gL10n->get('SYS_CHECKBOX_AUTOSAVE') . '</p>');

        $page->show();
    }
} catch (Throwable $e) {
    if (isset($_GET['mode']) && $_GET['mode'] === 'assign') {
        // ajax mode then only show text if error occurs
        $gMessage->showTextOnly();
    }
    handleException($e);
}
