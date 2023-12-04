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
 * start     : Position of query recordset where the visual output should start
 * cat_uuid  : show only roles of this category, if UUID is not set than show all roles
 * role_type : The type of roles that should be shown within this page.
 *             0 - inactive roles
 *             1 - active roles
 *             2 - event participation roles
 * show - card : (Default) Show all groups and roles in card view
 *      - permissions : Show permissions of all groups and roles in list view
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getStart    = admFuncVariableIsValid($_GET, 'start', 'int');
    $getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
    $getRoleType = admFuncVariableIsValid($_GET, 'role_type', 'int', array('defaultValue' => 1));
    $getShow     = admFuncVariableIsValid($_GET, 'show', 'string', array('defaultValue' => 'card', 'validValues' => array('card', 'permissions')));

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('groups_roles_enable_module')) {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        // => EXIT
    }

    // set headline
    switch ($getRoleType) {
        case ModuleGroupsRoles::ROLE_TYPE_INACTIVE:
            $headline = $gL10n->get('SYS_INACTIVE_GROUPS_ROLES');
            break;

        case ModuleGroupsRoles::ROLE_TYPE_ACTIVE:
            $headline = $gL10n->get('SYS_GROUPS_ROLES');
            break;

        case ModuleGroupsRoles::ROLE_TYPE_EVENT_PARTICIPATION:
            $headline = $gL10n->get('SYS_ROLES_CONFIRMATION_OF_PARTICIPATION');
            break;
    }

    if ($getShow === 'permissions') {
        if (!$gCurrentUser->manageRoles()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        $headline .= ' - ' . $gL10n->get('SYS_PERMISSIONS');
    }

    // only users with the right to assign roles can view inactive roles
    if (!$gCurrentUser->checkRolesRight('rol_assign_roles')) {
        $getRoleType = ModuleGroupsRoles::ROLE_TYPE_ACTIVE;
    }

    $category = new TableCategory($gDb);

    if (strlen($getCatUuid) > 1) {
        $category->readDataByUuid($getCatUuid);
        $headline .= ' - '.$category->getValue('cat_name');
    }

    // create html page object
    $groupsRoles = new ModuleGroupsRoles('admidio-groups-roles', $headline);

    if ($getShow === 'card') {
        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-users');
    } else {
        // In permission mode the navigation should continue
        $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_PERMISSIONS'));
    }


    if ($gCurrentUser->manageRoles()) {
        // show link to create new role
        $groupsRoles->addPageFunctionsMenuItem(
            'menu_item_groups_roles_add',
            $gL10n->get('SYS_CREATE_ROLE'),
            ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php',
            'fa-plus-circle'
        );

        if ($getShow === 'card') {
            // show permissions of all roles
            $groupsRoles->addPageFunctionsMenuItem(
                'menu_item_groups_roles_show_permissions',
                $gL10n->get('SYS_SHOW_PERMISSIONS'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('show' => 'permissions', 'cat_uuid' => $getCatUuid, 'role_type' => $getRoleType)),
                'fa-user-shield'
            );
        }

        // show link to maintain categories
        $groupsRoles->addPageFunctionsMenuItem(
            'menu_item_groups_roles_maintain_categories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'ROL')),
            'fa-th-large'
        );
    }

    // show link to create own list
    if ($gSettingsManager->getInt('groups_roles_edit_lists') === 1 // everyone
    || ($gSettingsManager->getInt('groups_roles_edit_lists') === 2 && $gCurrentUser->checkRolesRight('rol_edit_user')) // users with the right to edit all profiles
    || ($gSettingsManager->getInt('groups_roles_edit_lists') === 3 && $gCurrentUser->isAdministrator())) {
        $groupsRoles->addPageFunctionsMenuItem(
            'menu_item_groups_own_list',
            $gL10n->get('SYS_CONFIGURE_LISTS'),
            ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php',
            'fa-list-alt'
        );
    }

    // add filter navbar
    $groupsRoles->addJavascript(
        '
        $("#cat_uuid").change(function() {
            $("#navbar_filter_form").submit();
        });
        $("#role_type").change(function() {
            $("#navbar_filter_form").submit();
        });',
        true
    );

    // create filter menu with elements for category
    $filterNavbar = new HtmlNavbar('navbar_filter', '', null, 'filter');
    $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', $groupsRoles, array('type' => 'navbar', 'setFocus' => false));
    $form->addInput('show', '', $getShow, array('property' => HtmlForm::FIELD_HIDDEN));
    $form->addSelectBoxForCategories(
        'cat_uuid',
        $gL10n->get('SYS_CATEGORY'),
        $gDb,
        'ROL',
        HtmlForm::SELECT_BOX_MODUS_FILTER,
        array('defaultValue' => $getCatUuid)
    );
    if ($gCurrentUser->manageRoles()) {
        $form->addSelectBox(
            'role_type',
            $gL10n->get('SYS_ROLE_TYPES'),
            array(0 => $gL10n->get('SYS_INACTIVE_GROUPS_ROLES'), 1 => $gL10n->get('SYS_ACTIVE_GROUPS_ROLES'), 2 => $gL10n->get('SYS_ROLES_CONFIRMATION_OF_PARTICIPATION')),
            array('defaultValue' => $getRoleType)
        );
    }
    $filterNavbar->addForm($form->show());
    $groupsRoles->addHtml($filterNavbar->show());
    $groupsRoles->readData($getRoleType, $getCatUuid);

    if ($groupsRoles->countRoles() === 0) {
        if ($gValidLogin) {
            // If login valid, then show message for not available roles
            if ($getRoleType === ModuleGroupsRoles::ROLE_TYPE_ACTIVE) {
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

    if ($getShow === 'card') {
        $groupsRoles->createContentCards();
    } else {
        $groupsRoles->createContentPermissionsList();
    }

    $groupsRoles->show();
} catch (AdmException|Exception|SmartyException $e) {
    $gMessage->show($e->getMessage());
}
