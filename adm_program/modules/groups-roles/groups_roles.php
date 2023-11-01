<?php
/**
 ***********************************************************************************************
 * Show a list of all list roles
 *
 * @copyright 2004-2023 The Admidio Team
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

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start', 'int');
$getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
$getRoleType = admFuncVariableIsValid($_GET, 'role_type', 'int', array('defaultValue' => 1));
$getShow     = admFuncVariableIsValid($_GET, 'show', 'string', array('defaultValue' => 'card', 'validValues' => array('card', 'permissions')));

define('ROLE_TYPE_INACTIVE', 0);
define('ROLE_TYPE_ACTIVE', 1);
define('ROLE_TYPE_EVENT_PARTICIPATION', 2);

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('groups_roles_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// set headline
switch ($getRoleType) {
    case ROLE_TYPE_INACTIVE:
        $headline = $gL10n->get('SYS_INACTIVE_GROUPS_ROLES');
        break;

    case ROLE_TYPE_ACTIVE:
        $headline = $gL10n->get('SYS_GROUPS_ROLES');
        break;

    case ROLE_TYPE_EVENT_PARTICIPATION:
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
    $getRoleType = ROLE_TYPE_ACTIVE;
}

$category = new TableCategory($gDb);

if (strlen($getCatUuid) > 1) {
    $category->readDataByUuid($getCatUuid);
    $headline .= ' - '.$category->getValue('cat_name');
}

// New module list object
$lists = new ModuleLists();
$lists->setParameter('cat_id', $category->getValue('cat_id'));
$lists->setParameter('role_type', (int) $getRoleType);

// create html page object
$page = new HtmlPage('admidio-groups-roles', $headline);

if ($getShow === 'card') {
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-users');
} else {
    // In permission mode the navigation should continue
    $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_PERMISSIONS'));
}


if ($gCurrentUser->manageRoles()) {
    // show link to create new role
    $page->addPageFunctionsMenuItem(
        'menu_item_groups_roles_add',
        $gL10n->get('SYS_CREATE_ROLE'),
        ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php',
        'fa-plus-circle'
    );

    if ($getShow === 'card') {
        // show permissions of all roles
        $page->addPageFunctionsMenuItem(
            'menu_item_groups_roles_show_permissions',
            $gL10n->get('SYS_SHOW_PERMISSIONS'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('show' => 'permissions', 'cat_uuid' => $getCatUuid, 'role_type' => $getRoleType)),
            'fa-user-shield'
        );
    }

    // show link to maintain categories
    $page->addPageFunctionsMenuItem(
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
    $page->addPageFunctionsMenuItem(
        'menu_item_groups_own_list',
        $gL10n->get('SYS_CONFIGURE_LISTS'),
        ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php',
        'fa-list-alt'
    );
}

// add filter navbar
$page->addJavascript(
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
$filterNavbar = new HtmlNavbar('navbar_filter', null, null, 'filter');
$form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', $page, array('type' => 'navbar', 'setFocus' => false));
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
$page->addHtml($filterNavbar->show());

$previousCategoryId = 0;

// Get Lists
$getStart    = $lists->getStartElement();
$listsResult = $lists->getDataSet($getStart);

if ($listsResult['totalCount'] === 0) {
    if ($gValidLogin) {
        // If login valid, than show message for non available roles
        if ($getRoleType === ROLE_TYPE_ACTIVE) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS_VIEW_LIST'));
        // => EXIT
        } else {
            $gMessage->show($gL10n->get('PRO_NO_ROLES_VISIBLE'));
            // => EXIT
        }
    } else {
        // forward to login page
        require(__DIR__ . '/../../system/login_valid.php');
    }
}

if ($getShow === 'permissions') {
    // Create table
    $table = new HtmlTable('roles_table', $page, true, true);

    // create array with all column heading values
    $columnHeading = array(
        $gL10n->get('SYS_CATEGORY'),
        $gL10n->get('SYS_GROUPS_ROLES'),
        $gL10n->get('SYS_PERMISSIONS'),
        $gL10n->get('SYS_SEND_MAILS'),
        $gL10n->get('SYS_VIEW_ROLE_MEMBERSHIPS'),
        $gL10n->get('SYS_VIEW_PROFILES_OF_ROLE_MEMBERS'),
        $gL10n->get('SYS_LEADER'),
        '&nbsp;'
    );
    $table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'left', 'left', 'right'));
    $table->disableDatatablesColumnsSort(array(3, 7));
    $table->setDatatablesColumnsNotHideResponsive(array(7));
    $table->setDatatablesGroupColumn(1);
    $table->addRowHeadingByArray($columnHeading);
}

// Create role object
$role = new TableRoles($gDb);

foreach ($listsResult['recordset'] as $row) {
    // Put data to Roleobject
    $role->setArray($row);

    $catId = (int) $role->getValue('cat_id');
    $rolId = (int) $role->getValue('rol_id');
    $roleUuid = $role->getValue('rol_uuid');

    if ($getShow === 'card') {
        // if category is different than previous, close old and open new one
        if ($previousCategoryId !== $catId) {
            // close only if previous category is not 0
            if ($previousCategoryId !== 0) {
                $page->addHtml('</div>');
            }
            $page->addHtml('<h2>' . $role->getValue('cat_name') . '</h2>
            <div class="row admidio-margin-bottom">');
            $previousCategoryId = $catId;
        }

        $page->addHtml('
        <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card admidio-card" id="role_details_panel_'.$roleUuid.'">
            <div class="card-body d-flex flex-column" id="admRoleDetails'.$roleUuid.'">
                <h5 class="card-title">'. $role->getValue('rol_name'). '</h5>');

        $page->addHtml('<ul class="list-group list-group-flush">');
        $html = '';

        // send a mail to all role members
        if ($gCurrentUser->hasRightSendMailToRole($rolId) && $gSettingsManager->getBool('enable_mail_module')) {
            $html .= '
                        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('role_uuid' => $roleUuid)).'">'.
                            '<i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('SYS_EMAIL_TO_MEMBERS').'"></i></a>';
        }

        // show link to export vCard if user is allowed to see members and the role has members
        if ($row['num_members'] > 0 || $row['num_leader'] > 0) {
            $html .= '
                        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_function.php', array('mode' => '6', 'role_uuid' => $roleUuid)).'">'.
                            '<i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', array($role->getValue('rol_name'))).'"></i></a>';
        }

        // link to assign or remove members if you are allowed to do it
        if ($role->allowedToAssignMembers($gCurrentUser)) {
            $html .= '
                        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('role_uuid' => $roleUuid)).'">'.
                            '<i class="fas fa-user-plus" data-toggle="tooltip" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'"></i></a>';
        }

        if ($gCurrentUser->manageRoles()) {
            // set role active or inactive
            if ($getRoleType === ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
                $html .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'role_details_panel_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                            '<i class="fas fa-user-check" data-toggle="tooltip" title="'.$gL10n->get('SYS_ACTIVATE_ROLE').'"></i></a>';
            } elseif ($getRoleType === ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
                $html .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'role_details_panel_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                            '<i class="fas fa-user-slash" data-toggle="tooltip" title="'.$gL10n->get('SYS_DEACTIVATE_ROLE').'"></i></a>';
            }

            // edit roles of you are allowed to assign roles
            $html .= '
                        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $role->getValue('rol_uuid'))).'">'.
                            '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT_ROLE').'"></i></a>
                        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'role_details_panel_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                            '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_ROLE').'"></i></a>';
        }

        if (strlen($html) > 0) {
            $page->addHtml('<li class="list-group-item">' . $html . '</li>');
        }

        if (!empty($role->getValue('rol_description'))) {
            $roleDescription = strip_tags($role->getValue('rol_description'));

            if (strlen($roleDescription) > 200) {
                // read first 200 chars of text, then search for last space and cut the text there. After that add a "more" link
                $textPrev = substr($roleDescription, 0, 200);
                $maxPosPrev = strrpos($textPrev, ' ');
                $roleDescription = substr($textPrev, 0, $maxPosPrev).
                                ' <span class="collapse" id="viewdetails-'.$roleUuid.'">'.substr($roleDescription, $maxPosPrev).'.
                                </span> <a class="admidio-icon-link" data-toggle="collapse" data-target="#viewdetails-'.$roleUuid.'"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';
            }

            $page->addHtml('<li class="list-group-item">' . $roleDescription . '</li>');
        }

        // block with informations about events and meeting-point
        if ($role->getValue('rol_start_date') !== '' || $role->getValue('rol_weekday') > 0
            || $role->getValue('rol_start_time') !== '' || $role->getValue('rol_location') !== '') {
            $page->addHtml('<li class="list-group-item"><h6>'.$gL10n->get('DAT_DATES').' / '.$gL10n->get('SYS_MEETINGS').'</h6>');
            if ($role->getValue('rol_start_date') !== '') {
                $page->addHtml('<span class="d-block">'.$gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))).'</span>');
            }

            if ($role->getValue('rol_weekday') > 0 || !empty($role->getValue('rol_start_time'))) {
                $html = '';
                if ($role->getValue('rol_weekday') > 0) {
                    $html .= DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                }
                if (!empty($role->getValue('rol_start_time'))) {
                    $html .= $gL10n->get('SYS_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
                }
                $page->addHtml('<span class="d-block">'.$html.'</span>');
            }

            // Meeting point
            if (!empty($role->getValue('rol_location'))) {
                $page->addHtml('<span class="d-block"><i class="fas fa-map-marker-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_LOCATION').'"></i> '. $role->getValue('rol_location').'</span>');
            }
            $page->addHtml('</li>');
        }

        // show members fee
        if (!empty($role->getValue('rol_cost')) || $role->getValue('rol_cost_period') > 0) {
            $html = '';

            // Member fee
            if (!empty($role->getValue('rol_cost'))) {
                $html .= (float) $role->getValue('rol_cost').' '.$gSettingsManager->getString('system_currency');
            }

            // Contributory period
            if (!empty($role->getValue('rol_cost_period')) && $role->getValue('rol_cost_period') != 0) {
                $html .= ' - ' . TableRoles::getCostPeriods($role->getValue('rol_cost_period'));
            }

            $page->addHtml('<li class="list-group-item"><h6>' . $gL10n->get('SYS_CONTRIBUTION') . '</h6><span class="d-block">' . $html . '</span></li>');
        }

        // show count of members and leaders of this role
        $html = '';
        $htmlLeader = '';

        if ($role->getValue('rol_max_members') > 0) {
            $html .= $gL10n->get('SYS_MAX_PARTICIPANTS_OF_ROLE', array((int) $row['num_members'], (int) $role->getValue('rol_max_members')));
        } else {
            $html .= $row['num_members'] . ' ' . $gL10n->get('SYS_PARTICIPANTS');
        }

        if ($gCurrentUser->hasRightViewFormerRolesMembers($rolId) && $getRoleType === ROLE_TYPE_ACTIVE && $row['num_former'] > 0) {
            // show former members
            if ($row['num_former'] == 1) {
                $textFormerMembers = $gL10n->get('SYS_FORMER');
            } else {
                $textFormerMembers = $gL10n->get('SYS_FORMER_PL');
            }

            $html .= '&nbsp;&nbsp;(<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => $rolId, 'show_former_members' => 1)) . '">'.$row['num_former'].' '.$textFormerMembers.'</a>) ';
        }

        if ($row['num_leader'] > 0) {
            $htmlLeader = '<span class="d-block">' . $row['num_leader'] . ' ' . $gL10n->get('SYS_LEADERS') . '</span>';
        }

        $page->addHtml('
                    <li class="list-group-item"><span class="d-block">' . $html . '</span>' . $htmlLeader . '</li>
                </ul>

                <a class="btn btn-primary mt-auto" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => $rolId)) . '">' . $gL10n->get('SYS_SHOW_MEMBER_LIST') . '</a>
            </div>
        </div>
        </div>');
    } else {
        $assignRoles        = '';
        $listView           = '';
        $linkAdministration = '';

        // Add data to role object
        $role->setArray($row);

        if ($role->getValue('rol_assign_roles') == 1) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-user-tie" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_ASSIGN_ROLES').'"></i>';
        }
        if ($role->getValue('rol_all_lists_view') == 1) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-list" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW').'"></i>';
        }
        if ($role->getValue('rol_approve_users') == 1) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_APPROVE_USERS').'"></i>';
        }
        if ($role->getValue('rol_mail_to_all') == 1) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_MAIL_TO_ALL').'"></i>';
        }
        if ($role->getValue('rol_edit_user') == 1) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-user-friends" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_EDIT_USER').'"></i>';
        }
        if ($role->getValue('rol_profile') == 1) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-user" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_PROFILE').'"></i>';
        }
        if ($role->getValue('rol_announcements') == 1 && (int) $gSettingsManager->get('enable_announcements_module') > 0) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-newspaper" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_ANNOUNCEMENTS').'"></i>';
        }
        if ($role->getValue('rol_dates') == 1 && (int) $gSettingsManager->get('enable_dates_module') > 0) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-calendar-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_DATES').'"></i>';
        }
        if ($role->getValue('rol_photo') == 1 && (int) $gSettingsManager->get('enable_photo_module') > 0) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-image" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_PHOTOS').'"></i>';
        }
        if ($role->getValue('rol_documents_files') == 1 && (int) $gSettingsManager->getBool('documents_files_enable_module')) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_DOCUMENTS_FILES').'"></i>';
        }
        if ($role->getValue('rol_guestbook') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-book" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_GUESTBOOK').'"></i>';
        }
        // If allowed to write anonymous guestbook entries, then we don´t need to set rights for the roles
        if ($role->getValue('rol_guestbook_comments') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0 && !$gSettingsManager->getBool('enable_gbook_comments4all')) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-comment" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS').'"></i>';
        }
        if ($role->getValue('rol_weblinks') == 1 && (int) $gSettingsManager->get('enable_weblinks_module') > 0) {
            $assignRoles .= '<i class="admidio-icon-chain fas fa-link" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_WEBLINKS').'"></i>';
        }
        // if no assigned roles
        if ($assignRoles === '') {
            $assignRoles = '&nbsp;';
        }

        $viewEmail = '';
        $viewMemberships  = '';
        $viewProfiles = '';
        $leaderRights = '';

        switch ($role->getValue('rol_mail_this_role')) {
            case 0:
                $viewEmail = 'SYS_NOBODY';
                break;
            case 1:
                $viewEmail = 'SYS_ROLE_MEMBERS';
                break;
            case 2:
                $viewEmail = 'ORG_REGISTERED_USERS';
                break;
            case 3:
                $viewEmail = 'SYS_ALSO_VISITORS';
                break;
        }

        switch ($role->getValue('rol_view_memberships')) {
            case 0:
                $viewMemberships = 'SYS_NOBODY';
                break;
            case 1:
                $viewMemberships = 'SYS_ROLE_MEMBERS';
                break;
            case 2:
                $viewMemberships = 'ORG_REGISTERED_USERS';
                break;
            case 3:
                $viewMemberships = 'SYS_LEADERS';
                break;
        }

        switch ($role->getValue('rol_view_members_profiles')) {
            case 0:
                $viewProfiles = 'SYS_NOBODY';
                break;
            case 1:
                $viewProfiles = 'SYS_ROLE_MEMBERS';
                break;
            case 2:
                $viewProfiles = 'ORG_REGISTERED_USERS';
                break;
            case 3:
                $viewMemberships = 'SYS_LEADERS';
                break;
        }

        switch ($role->getValue('rol_leader_rights')) {
            case 0:
                $leaderRights = 'SYS_NO_ADDITIONAL_RIGHTS';
                break;
            case 1:
                $leaderRights = 'SYS_ASSIGN_MEMBERS';
                break;
            case 2:
                $leaderRights = 'SYS_EDIT_MEMBERS';
                break;
            case 3:
                $leaderRights = 'SYS_ASSIGN_EDIT_MEMBERS';
                break;
        }

        $rolId = (int) $role->getValue('rol_id');
        $rolName = $role->getValue('rol_name');

        $linkAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId)).'">'.
                                    '<i class="fas fa-list-alt" data-toggle="tooltip" title="'.$gL10n->get('PRO_SHOW_ROLE_MEMBERSHIP').'"></i></a>';
        if ($getRoleType === ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
            $linkAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'row_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                '<i class="fas fa-user-check" data-toggle="tooltip" title="'.$gL10n->get('SYS_ACTIVATE_ROLE').'"></i></a>';
        } elseif ($getRoleType === ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
            $linkAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'row_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                '<i class="fas fa-user-slash" data-toggle="tooltip" title="'.$gL10n->get('SYS_DEACTIVATE_ROLE').'"></i></a>';
        }

        $linkAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                    data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'row_'.$roleUuid, 'name' => $rolName, 'database_id' => $roleUuid)).'">'.
                                    '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_ROLE').'"></i></a>';

        // create array with all column values
        $columnValues = array(
            array('value' => $role->getValue('cat_name'), 'order' => (int) $role->getValue('cat_sequence')),
            '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $roleUuid)).'">'.$rolName.'</a>',
            $assignRoles,
            $gL10n->get($viewEmail),
            $gL10n->get($viewMemberships),
            $gL10n->get($viewProfiles),
            $gL10n->get($leaderRights),
            $linkAdministration
        );

        $table->addRowByArray($columnValues, 'row_'. $roleUuid);
    }
}

if ($getShow === 'card') {
    if ($listsResult['numResults'] > 0) {
        $page->addHtml('</div>');
    }
} else {
    $page->addHtml($table->show());
}

$page->show();
