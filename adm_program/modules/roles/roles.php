<?php
/**
 ***********************************************************************************************
 * Show all roles with their individual permissions
 *
 * @copyright 2004-2019 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * inactive:  false - (Default) show all active roles
 *            true  - show all inactive roles
 * events:    false - (Default) show all event roles
 *            true  - show all roles except event roles
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getInactive  = admFuncVariableIsValid($_GET, 'inactive',  'bool');
$getEvents    = admFuncVariableIsValid($_GET, 'events',    'bool');

// only users with the special right are allowed to manage roles
if(!$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('ROL_ROLE_ADMINISTRATION');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

unset($_SESSION['roles_request']);

// per default show active and not event roles
$sqlRolesStatus = ' AND rol_valid   = \'1\'
                    AND cat_name_intern <> \'EVENTS\' ';

if($getInactive)
{
    $activeRolesLinkDescription = $gL10n->get('ROL_ACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_INACTIV_ROLES');
    $activeRolesImage = 'fa-user-tie';
    $activeRolesFlag  = '0';
    $sqlRolesStatus   = ' AND rol_valid = \'0\'
                          AND cat_name_intern <> \'EVENTS\' ';
}
else
{
    $activeRolesLinkDescription = $gL10n->get('ROL_INACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_ACTIV_ROLES');
    $activeRolesImage = 'fa-user-secret';
    $activeRolesFlag  = '1';
}

if($getEvents)
{
    if((int) $gSettingsManager->get('enable_dates_module') === 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $eventsRolesLinkDescription = $gL10n->get('ROL_ACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_ROLES_CONFIRMATION_OF_PARTICIPATION');
    $eventsRolesImage = 'fa-user-tie';
    $eventsRolesFlag  = '0';
    // in events mode show active and inactive events roles
    $sqlRolesStatus   = ' AND cat_name_intern = \'EVENTS\' ';
}
else
{
    $eventsRolesLinkDescription = $gL10n->get('ROL_ROLES_CONFIRMATION_OF_PARTICIPATION');
    $eventsRolesImage = 'fa-calendar-alt';
    $eventsRolesFlag  = '1';
}

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('$(".admidio-group-heading").click(function() { showHideBlock($(this).attr("id")); });', true);

// get module menu
$rolesMenu = $page->getMenu();

// define link to create new profile field
$rolesMenu->addItem(
    'menu_item_new_role', ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php',
    $gL10n->get('SYS_CREATE_ROLE'), 'fa-plus-circle'
);
// define link to maintain categories
$rolesMenu->addItem(
    'menu_item_maintain_category', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'ROL')),
    $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'fa-th-large'
);
// define link to show inactive roles
$rolesMenu->addItem(
    'menu_item_inactive_role', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/roles/roles.php', array('inactive' => $activeRolesFlag)),
    $activeRolesLinkDescription, $activeRolesImage
);

if((int) $gSettingsManager->get('enable_dates_module') > 0)
{
    // if event module is enabled then define link to confirmation roles of event participations
    $rolesMenu->addItem(
        'menu_item_hidden_role', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/roles/roles.php', array('events' => $eventsRolesFlag)),
        $eventsRolesLinkDescription, $eventsRolesImage
    );
}

// Create table
$table = new HtmlTable('roles_table', $page, true, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_CATEGORY'),
    $listDescription,
    $gL10n->get('SYS_PERMISSIONS'),
    $gL10n->get('ROL_SEND_MAILS'),
    $gL10n->get('ROL_SEE_ROLE_MEMBERSHIP'),
    $gL10n->get('SYS_LEADER'),
    $gL10n->get('SYS_FEATURES')
);
$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'left', 'right'));
$table->disableDatatablesColumnsSort(array(3, 7));
$table->setDatatablesGroupColumn(1);
$table->addRowHeadingByArray($columnHeading);

// list all roles group by category
$sql = 'SELECT *
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE cat_type = \'ROL\'
           AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
               OR cat_org_id IS NULL )
               '.$sqlRolesStatus.'
      ORDER BY cat_sequence ASC, rol_name ASC';
$rolStatement = $gDb->queryPrepared($sql, array((int) $gCurrentOrganization->getValue('org_id')));

// Create role object
$role = new TableRoles($gDb);

while($row = $rolStatement->fetch())
{
    $assignRoles        = '';
    $listView           = '';
    $linkAdministration = '';

    // Add data to role object
    $role->setArray($row);

    if($role->getValue('rol_assign_roles') == 1)
    {
        $assignRoles .= '<i class="fas fa-user-tie" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'"></i>';
    }
    if($role->getValue('rol_all_lists_view') == 1)
    {
        $assignRoles .= '<i class="fas fa-list" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'"></i>';
    }
    if($role->getValue('rol_approve_users') == 1)
    {
        $assignRoles .= '<i class="fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'"></i>';
    }
    if($role->getValue('rol_mail_to_all') == 1)
    {
        $assignRoles .= '<i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'"></i>';
    }
    if($role->getValue('rol_edit_user') == 1)
    {
        $assignRoles .= '<i class="fas fa-user-friends" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'"></i>';
    }
    if($role->getValue('rol_profile') == 1)
    {
        $assignRoles .= '<i class="fas fa-user" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'"></i>';
    }
    if($role->getValue('rol_announcements') == 1 && (int) $gSettingsManager->get('enable_announcements_module') > 0)
    {
        $assignRoles .= '<i class="fas fa-newspaper" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'"></i>';
    }
    if($role->getValue('rol_dates') == 1 && (int) $gSettingsManager->get('enable_dates_module') > 0)
    {
        $assignRoles .= '<i class="fas fa-calendar-alt" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_DATES').'"></i>';
    }
    if($role->getValue('rol_photo') == 1 && (int) $gSettingsManager->get('enable_photo_module') > 0)
    {
        $assignRoles .= '<i class="fas fa-image" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'"></i>';
    }
    if($role->getValue('rol_download') == 1 && (int) $gSettingsManager->getBool('enable_download_module'))
    {
        $assignRoles .= '<i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'"></i>';
    }
    if($role->getValue('rol_guestbook') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0)
    {
        $assignRoles .= '<i class="fas fa-book" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'"></i>';
    }
    // If allowed to write anonymous guestbook entries, then we don´t need to set rights for the roles
    if($role->getValue('rol_guestbook_comments') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0 && !$gSettingsManager->getBool('enable_gbook_comments4all'))
    {
        $assignRoles .= '<i class="fas fa-comment" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'"></i>';
    }
    if($role->getValue('rol_weblinks') == 1 && (int) $gSettingsManager->get('enable_weblinks_module') > 0)
    {
        $assignRoles .= '<i class="fas fa-link" data-toggle="tooltip" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'"></i>';
    }
    // if no assigned roles
    if($assignRoles === '')
    {
        $assignRoles = '&nbsp;';
    }

    $viewEmail = '';
    $viewRole  = '';
    $leaderRights = '';

    switch ($role->getValue('rol_mail_this_role'))
    {
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

    switch ($role->getValue('rol_this_list_view'))
    {
        case 0:
            $viewRole = 'SYS_NOBODY';
            break;
        case 1:
            $viewRole = 'SYS_ROLE_MEMBERS';
            break;
        case 2:
            $viewRole = 'ORG_REGISTERED_USERS';
            break;
    }

    switch ($role->getValue('rol_leader_rights'))
    {
        case 0:
            $leaderRights = 'ROL_NO_ADDITIONAL_RIGHTS';
            break;
        case 1:
            $leaderRights = 'SYS_ASSIGN_MEMBERS';
            break;
        case 2:
            $leaderRights = 'SYS_EDIT_MEMBERS';
            break;
        case 3:
            $leaderRights = 'ROL_ASSIGN_EDIT_MEMBERS';
            break;
    }

    $rolId = (int) $role->getValue('rol_id');
    $rolName = $role->getValue('rol_name');

    $linkAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId)).'">'.
                                '<i class="fas fa-list" data-toggle="tooltip" title="'.$gL10n->get('ROL_SHOW_MEMBERS').'"></i></a>';

    if(!$getInactive)
    {
        $linkAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php', array('rol_id' => $rolId)).'">'.
                                    '<i class="fas fa-user-plus" data-toggle="tooltip" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'"></i></a>';
    }

    $linkAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php', array('mode' => '8', 'rol_id'. $rolId)).'">'.
                                '<i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', array($rolName)).'"></i></a>';

    if($role->getValue('cat_name_intern') !== 'EVENTS')
    {
        if($getInactive)
        {
            $linkAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'row_'.$rolId, 'name' => $rolName, 'database_id' => $rolId)).'">'.
                                        '<i class="fas fa-user-tie" data-toggle="tooltip" title="'.$gL10n->get('ROL_ENABLE_ROLE').'"></i></a>';
        }
        else
        {
            $linkAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'row_'.$rolId, 'name' => $rolName, 'database_id' => $rolId)).'">'.
                                        '<i class="fas fa-user-secret" data-toggle="tooltip" title="'.$gL10n->get('ROL_DISABLE_ROLE').'"></i></a>';
        }
    }

    if($role->getValue('rol_administrator') == 1)
    {
        $linkAdministration .= '<i class="fas fa-trash invisible"></i>';
    }
    else
    {
        $linkAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'row_'.$rolId, 'name' => $rolName, 'database_id' => $rolId)).'">'.
                                    '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('ROL_ROLE_DELETE').'"></i></a>';
    }

    // create array with all column values
    $columnValues = array(
        array('value' => $role->getValue('cat_name'), 'order' => (int) $role->getValue('cat_sequence')),
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php', array('rol_id' => $rolId)).'" title="'.$role->getValue('rol_description').'">'.$rolName.'</a>',
        $assignRoles,
        $gL10n->get($viewEmail),
        $gL10n->get($viewRole),
        $gL10n->get($leaderRights),
        $linkAdministration
    );

    $table->addRowByArray($columnValues, 'row_'. $rolId);
}

$page->addHtml($table->show());

// show html of complete page
$page->show();
