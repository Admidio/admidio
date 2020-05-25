<?php
/**
 ***********************************************************************************************
 * Show a list of all list roles
 *
 * @copyright 2004-2019 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * start     : Position of query recordset where the visual output should start
 * cat_id    : show only roles of this category id, if id is not set than show all roles
 * role_type : The type of roles that should be shown within this page.
 *             0 - inactive roles
 *             1 - active roles
 *             2 - event participation roles
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start',     'int');
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id',    'int');
$getRoleType = admFuncVariableIsValid($_GET, 'role_type', 'int', array('defaultValue' => 1));

define('ROLE_TYPE_INACTIVE', 0);
define('ROLE_TYPE_ACTIVE', 1);
define('ROLE_TYPE_EVENT_PARTICIPATION', 2);

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('lists_enable_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// set headline
switch($getRoleType)
{
    case ROLE_TYPE_INACTIVE:
        $headline = $gL10n->get('SYS_INACTIVE_GROUPS_ROLES');
        break;

    case ROLE_TYPE_ACTIVE:
        $headline = $gL10n->get('SYS_GROUPS_ROLES');
        break;

    case ROLE_TYPE_EVENT_PARTICIPATION:
        $headline = $gL10n->get('ROL_ROLES_CONFIRMATION_OF_PARTICIPATION');
        break;
}

// only users with the right to assign roles can view inactive roles
if(!$gCurrentUser->checkRolesRight('rol_assign_roles'))
{
    $getRoleType = ROLE_TYPE_ACTIVE;
}

// New Modulelist object
$lists = new ModuleLists();
$lists->setParameter('cat_id', $getCatId);
$lists->setParameter('role_type', (int) $getRoleType);

if($getCatId > 0)
{
    $category = new TableCategory($gDb, $getCatId);
    $headline .= ' - '.$category->getValue('cat_name');
}

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

if($gCurrentUser->manageRoles())
{
    // show link to create new role
    $page->addPageFunctionsMenuItem('menu_item_groups_roles_add', $gL10n->get('SYS_CREATE_ROLE'),
        ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/roles_new.php', 'fa-plus-circle');

     // show link to maintain categories
    $page->addPageFunctionsMenuItem('menu_item_groups_roles_maintain_categories', $gL10n->get('SYS_MAINTAIN_CATEGORIES'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'ROL')),
        'fa-th-large');
}

// show link to create own list
$page->addPageFunctionsMenuItem('menu_item_groups_own_list', $gL10n->get('LST_CREATE_OWN_LIST'),
    ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', 'fa-list-alt');

// add filter navbar
$page->addJavascript('
    $("#cat_id").change(function() {
        $("#navbar_filter_form").submit();
    });
    $("#role_type").change(function() {
        $("#navbar_filter_form").submit();
    });',
    true
);

// create filter menu with elements for category
$filterNavbar = new HtmlNavbar('navbar_filter', null, null, 'filter');
$form = new HtmlForm('navbar_filter_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('role_type' => (int) $getRoleType)), $page, array('type' => 'navbar', 'setFocus' => false));
$form->addSelectBoxForCategories(
    'cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', HtmlForm::SELECT_BOX_MODUS_FILTER,
    array('defaultValue' => $getCatId));
if($gCurrentUser->manageRoles())
{
    $form->addSelectBox(
        'role_type', $gL10n->get('SYS_ROLE_TYPES'), array(0 => $gL10n->get('SYS_INACTIVE_GROUPS_ROLES'), 1 => $gL10n->get('SYS_ACTIVE_GROUPS_ROLES'), 2 => $gL10n->get('ROL_ROLES_CONFIRMATION_OF_PARTICIPATION')),
        array('defaultValue' => $getRoleType));
}
$filterNavbar->addForm($form->show());
$page->addHtml($filterNavbar->show());

$previousCategoryId = 0;

// Get Lists
$getStart    = $lists->getStartElement();
$listsResult = $lists->getDataSet($getStart);

if($listsResult['totalCount'] === 0)
{
    if($gValidLogin)
    {
        // If login valid, than show message for non available roles
        if($getRoleType === ROLE_TYPE_ACTIVE)
        {
            $gMessage->show($gL10n->get('LST_NO_RIGHTS_VIEW_LIST'));
            // => EXIT
        }
        else
        {
            $gMessage->show($gL10n->get('PRO_NO_ROLES_VISIBLE'));
            // => EXIT
        }
    }
    else
    {
        // forward to login page
        require(__DIR__ . '/../../system/login_valid.php');
    }
}

// Create role object
$role = new TableRoles($gDb);

foreach($listsResult['recordset'] as $row)
{
    // Put data to Roleobject
    $role->setArray($row);

    $catId = (int) $role->getValue('cat_id');
    $rolId = (int) $role->getValue('rol_id');

    // if category is different than previous, close old and open new one
    if($previousCategoryId !== $catId)
    {
        // close only if previous category is not 0
        if($previousCategoryId !== 0)
        {
            $page->addHtml('</div>');
        }
        $page->addHtml('<h2>' . $role->getValue('cat_name') . '</h2>
        <div class="row mb-5">');
        $previousCategoryId = $catId;
    }

    $page->addHtml('
    <div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="card admidio-roles" id="role_details_panel_'.$rolId.'">
        <div class="card-body" id="admRoleDetails'.$rolId.'">
            <h5 class="card-title">'. $role->getValue('rol_name'). '</h5>');

            $page->addHtml('<ul class="list-group list-group-flush">');
                $html = '';

                // send a mail to all role members
                if($gCurrentUser->hasRightSendMailToRole($rolId) && $gSettingsManager->getBool('enable_mail_module'))
                {
                    $html .= '
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('rol_id' => $rolId)).'">'.
                        '<i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'"></i></a>';
                }

                // show link to export vCard if user is allowed to see members and the role has members
                if($row['num_members'] > 0 || $row['num_leader'] > 0)
                {
                    $html .= '
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php', array('mode' => '8', 'rol_id' => $rolId)).'">'.
                        '<i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', array($role->getValue('rol_name'))).'"></i></a>';
                }

                // link to assign or remove members if you are allowed to do it
                if($role->allowedToAssignMembers($gCurrentUser))
                {
                    $html .= '
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('rol_id' => $rolId)).'">'.
                        '<i class="fas fa-user-plus" data-toggle="tooltip" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'"></i></a>';
                }

                // edit roles of you are allowed to assign roles
                if($gCurrentUser->manageRoles())
                {
                    if($getRoleType === ROLE_TYPE_INACTIVE)
                    {
                        $html .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'role_details_panel_'.$rolId, 'name' => $role->getValue('rol_name'), 'database_id' => $rolId)).'">'.
                                        '<i class="fas fa-users-tie" data-toggle="tooltip" title="'.$gL10n->get('ROL_ENABLE_ROLE').'"></i></a>';
                    }
                    elseif($getRoleType === ROLE_TYPE_ACTIVE)
                    {
                        $html .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'role_details_panel_'.$rolId, 'name' => $role->getValue('rol_name'), 'database_id' => $rolId)).'">'.
                                        '<i class="fas fa-user-secret" data-toggle="tooltip" title="'.$gL10n->get('ROL_DISABLE_ROLE').'"></i></a>';
                    }

                    $html .= '
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/roles_new.php', array('rol_id' => $rolId)).'">'.
                        '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('ROL_EDIT_ROLE').'"></i></a>
                    <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'role_details_panel_'.$rolId, 'name' => $role->getValue('rol_name'), 'database_id' => $rolId)).'">'.
                        '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('ROL_ROLE_DELETE').'"></i></a>';
                }

                if(strlen($html) > 0)
                {
                     $page->addHtml('<li class="list-group-item">' . $html . '</li>');
                }

                if(strlen($role->getValue('rol_description')) > 0)
                {
                    $page->addHtml('<li class="list-group-item">' . $role->getValue('rol_description') . '</li>');
                }

                // block with informations about events and meeting-point
                if(strlen($role->getValue('rol_start_date')) > 0 || $role->getValue('rol_weekday') > 0
                || strlen($role->getValue('rol_start_time')) > 0 || strlen($role->getValue('rol_location')) > 0)
                {
                    $page->addHtml('<li class="list-group-item"><h6>'.$gL10n->get('DAT_DATES').' / '.$gL10n->get('ROL_MEETINGS').'</h6>');
                        if(strlen($role->getValue('rol_start_date')) > 0)
                        {
                            $page->addHtml('<span class="d-block">'.$gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))).'</span>');
                        }

                        if($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
                        {
                            $html = '';
                            if($role->getValue('rol_weekday') > 0)
                            {
                                $html .= DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                            }
                            if(strlen($role->getValue('rol_start_time')) > 0)
                            {
                                $html .= $gL10n->get('LST_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
                            }
                            $page->addHtml('<span class="d-block">'.$html.'</span>');
                        }

                        // Meeting point
                        if(strlen($role->getValue('rol_location')) > 0)
                        {
                            $page->addHtml('<span class="d-block"><i class="fas fa-map-marker-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_LOCATION').'"></i> '. $role->getValue('rol_location').'</span>');
                        }
                    $page->addHtml('</li>');
                }

                // show members fee
                if(strlen($role->getValue('rol_cost')) > 0
                || (strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0))
                {
                    $html = '';

                    // Member fee
                    if(strlen($role->getValue('rol_cost')) > 0)
                    {
                        $html .= (float) $role->getValue('rol_cost').' '.$gSettingsManager->getString('system_currency');
                    }

                    // Contributory period
                    if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                    {
                        $html .= ' - ' . TableRoles::getCostPeriods($role->getValue('rol_cost_period'));
                    }

                    $page->addHtml('<li class="list-group-item"><h6>' . $gL10n->get('SYS_CONTRIBUTION') . '</h6><span class="d-block">' . $html . '</span>' . $htmlLeader . '</li>');
                }

                // show count of members and leaders of this role
                $html = '';
                $htmlLeader = '';

                if($role->getValue('rol_max_members') > 0)
                {
                    $html .= $gL10n->get('SYS_MAX_PARTICIPANTS_OF_ROLE', array((int) $row['num_members'], (int) $role->getValue('rol_max_members')));
                }
                else
                {
                    $html .= $row['num_members'] . ' ' . $gL10n->get('SYS_PARTICIPANTS');
                }

                if($gCurrentUser->hasRightViewFormerRolesMembers($rolId) && $getRoleType === ROLE_TYPE_ACTIVE && $row['num_former'] > 0)
                {
                    // show former members
                    if($row['num_former'] == 1)
                    {
                        $textFormerMembers = $gL10n->get('SYS_FORMER');
                    }
                    else
                    {
                        $textFormerMembers = $gL10n->get('SYS_FORMER_PL');
                    }

                    $html .= '&nbsp;&nbsp;('.$row['num_former'].' '.$textFormerMembers.') ';
                }

                if($row['num_leader'] > 0)
                {
                    $htmlLeader = '<span class="d-block">' . $row['num_leader'] . ' ' . $gL10n->get('SYS_LEADERS') . '</span>';
                }

                $page->addHtml('
                <li class="list-group-item"><span class="d-block">' . $html . '</span>' . $htmlLeader . '</li>
            </ul>

            <a class="btn btn-primary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId)) . '">' . $gL10n->get('SYS_SHOW_MEMBER_LIST') . '</a>
        </div>
    </div>
    </div>');
}

if($listsResult['numResults'] > 0)
{
    $page->addHtml('</div>');
}

// If necessary show links to navigate to next and previous recordsets of the query
$baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('cat_id' => $getCatId, 'role_type' => (int) $getRoleType));
$page->addHtml(admFuncGeneratePagination($baseUrl, $listsResult['totalCount'], $gSettingsManager->getInt('lists_roles_per_page'), $getStart));

$page->show();
