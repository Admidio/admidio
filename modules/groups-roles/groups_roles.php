<?php
/**
 ***********************************************************************************************
 * Show a list of all list roles
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode - card       : (Default) Show all groups and roles in card view
 *      - permissions : Show permissions of all groups and roles in list view
 *      - edit       : Show dialog to create or edit a role.
 *      - save       : Save data of role form dialog
 *      - delete     : delete role
 *      - activate   : set role active
 *      - deactivate : set role inactive
 *      - export     : Export vCard of role
 * cat_uuid  : show only roles of this category, if UUID is not set than show all roles
 * role_uuid: UUID of role, that should be edited
 * role_type : The type of roles that should be shown within this page.
 *             0 - inactive roles
 *             1 - active roles
 *             2 - event participation roles
 ***********************************************************************************************
 */

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Service\RolesService;
use Admidio\UI\Presenter\GroupsRolesPresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'card', 'validValues' => array('card', 'permissions', 'edit', 'save', 'delete', 'activate', 'deactivate', 'export')));
    $getCategoryUUID  = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getRoleUUID = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid');
    $getRoleType = admFuncVariableIsValid($_GET, 'role_type', 'int', array('defaultValue' => 1));

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('groups_roles_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if ($getMode !== 'card') {
        // only users with the special right are allowed to manage roles
        if (!$gCurrentUser->isAdministratorRoles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    if (in_array($getMode, array('card', 'permissions'))) {
        // set headline
        switch ($getRoleType) {
            case GroupsRolesPresenter::ROLE_TYPE_INACTIVE:
                $headline = $gL10n->get('SYS_INACTIVE_GROUPS_ROLES');
                break;

            case GroupsRolesPresenter::ROLE_TYPE_ACTIVE:
                $headline = $gL10n->get('SYS_GROUPS_ROLES');
                break;

            case GroupsRolesPresenter::ROLE_TYPE_EVENT_PARTICIPATION:
                $headline = $gL10n->get('SYS_ROLES_CONFIRMATION_OF_PARTICIPATION');
                break;
        }

        if ($getMode === 'permissions') {
            $headline .= ' - ' . $gL10n->get('SYS_PERMISSIONS');
        }

        // only users with the right to assign roles can view inactive roles
        if (!$gCurrentUser->checkRolesRight('rol_assign_roles')) {
            $getRoleType = GroupsRolesPresenter::ROLE_TYPE_ACTIVE;
        }

        $category = new Category($gDb);

        if (strlen($getCategoryUUID) > 1) {
            $category->readDataByUuid($getCategoryUUID);
            $headline .= ' - ' . $category->getValue('cat_name');
        }

        if ($getMode === 'card') {
            // Navigation of the module starts here
            $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-people-fill');
        } else {
            // In permission mode the navigation should continue
            $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_PERMISSIONS'));
        }

        // create html page object
        $groupsRoles = new GroupsRolesPresenter('adm_groups_roles', $headline);

        $rolesService = new RolesService($gDb);
        $data = $rolesService->findAll($getRoleType, $getCategoryUUID);

        if (count($data) === 0) {
            if ($gValidLogin) {
                // If login valid, then show message for not available roles
                if ($getRoleType === GroupsRolesPresenter::ROLE_TYPE_ACTIVE) {
                    $gMessage->show($gL10n->get('SYS_NO_RIGHTS_VIEW_LIST'));
                    // => EXIT
                } else {
                    $gMessage->show($gL10n->get('SYS_NO_ROLES_VISIBLE'));
                    // => EXIT
                }
            } else {
                // forward to login page
                require(__DIR__ . '/../../system/login_valid.php');
            }
        }
    }

    switch ($getMode) {
        case 'card':
            $groupsRoles->createCards($getCategoryUUID, $getRoleType);
            $groupsRoles->show();
            break;

        case 'permissions':
            $groupsRoles->createPermissionsList($getCategoryUUID, $getRoleType);
            $groupsRoles->show();
            break;

        case 'edit':
            if ($getRoleUUID !== '') {
                $headline = $gL10n->get('SYS_EDIT_ROLE');
            } else {
                $headline = $gL10n->get('SYS_CREATE_ROLE');
            }

            $gNavigation->addUrl(CURRENT_URL, $headline);
            $groupsRoles = new GroupsRolesPresenter('adm_groups_roles_edit', $headline);
            $groupsRoles->createEditForm($getRoleUUID);
            $groupsRoles->show();
            break;

        case 'save':
            $groupsRoles = new RolesService($gDb, $getRoleUUID);
            $groupsRoles->save();
            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // delete role from database
            $role = new Role($gDb);
            $role->readDataByUuid($getRoleUUID);
            if ($role->delete()) {
                echo json_encode(array('status' => 'success'));
            }
            break;

        case 'activate':
            // set role active
            $role = new Role($gDb);
            $role->readDataByUuid($getRoleUUID);
            $role->activate();
            echo 'done';
            break;

        case 'deactivate':
            // set role inactive
            $role = new Role($gDb);
            $role->readDataByUuid($getRoleUUID);
            $role->deactivate();
            echo 'done';
            break;

        case 'export':
            // Export every member of a role into one vCard file
            $groupsRoles = new RolesService($gDb, $getRoleUUID);
            $groupsRoles->export();
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('save', 'delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
