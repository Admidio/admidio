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
 * start    : Position of query recordset where the visual output should start
 * cat_id   : show only roles of this category id, if id is not set than show all roles
 * active_role : true  - (Default) aktive Rollen auflisten
 *               false - inaktive Rollen auflisten
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getStart      = admFuncVariableIsValid($_GET, 'start',       'int');
$getCatId      = admFuncVariableIsValid($_GET, 'cat_id',      'int');
$getActiveRole = admFuncVariableIsValid($_GET, 'active_role', 'bool', array('defaultValue' => true));

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('lists_enable_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// set headline
if($getActiveRole)
{
    $headline = $gL10n->get('SYS_GROUPS_ROLES');
}
else
{
    $headline = $gL10n->get('SYS_INACTIVE_GROUPS_ROLES');
}

// only users with the right to assign roles can view inactive roles
if(!$gCurrentUser->checkRolesRight('rol_assign_roles'))
{
    $getActiveRole = true;
}

// New Modulelist object
$lists = new ModuleLists();
$lists->setParameter('cat_id', $getCatId);
$lists->setParameter('active_role', (int) $getActiveRole);

if($getCatId > 0)
{
    $category = new TableCategory($gDb, $getCatId);
    $headline .= ' - '.$category->getValue('cat_name');
}

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

if(!$gSettingsManager->getBool('lists_hide_overview_details'))
{
    $page->addJavascript('$(".collapse").collapse();', true);
}

$page->addJavascript('
    $(".panel-collapse select").change(function() {
        elementId = $(this).attr("id");
        roleId    = elementId.substr(elementId.search(/_/) + 1);

        if ($(this).val() === "mylist") {
            self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/lists/mylist.php', array('active_role' => (int) $getActiveRole)) . '&rol_ids=" + roleId;
        } else {
            self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/lists/lists_show.php', array('mode' => 'html')) . '&lst_id=" + $(this).val() + "&rol_ids=" + roleId;
        }
    });',
    true
);

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

// add filter navbar
$page->addJavascript('
    $("#cat_id").change(function() {
        $("#navbar_filter_form").submit();
    });',
    true
);

// create filter menu with elements for category
$filterNavbar = new HtmlNavbar('navbar_filter', null, null, 'filter');
$form = new HtmlForm('navbar_filter_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('active_role' => (int) $getActiveRole)), $page, array('type' => 'navbar', 'setFocus' => false));
$form->addSelectBoxForCategories(
    'cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', HtmlForm::SELECT_BOX_MODUS_FILTER,
    array('defaultValue' => $getCatId));
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
        if($getActiveRole)
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
        /*$page->addHtml('<div class="card">
            <div class="card-header">' . $role->getValue('cat_name') . '</div>
            <div class="card-body">
                <div class="panel-group" id="accordion_'.$catId.'">');*/
        $page->addHtml('<h2>' . $role->getValue('cat_name') . '</h2>
        <div class="row mb-5">');
        $previousCategoryId = $catId;
    }

    $page->addHtml('
    <div class="card admidio-roles col-sm-6 col-md-3" id="role_details_panel_'.$rolId.'">
        <div class="card-header">
            <div class="float-left">
                <a data-toggle="collapse" data-parent="#accordion_'.$catId.'" href="#collapse_'.$rolId.'">
                    '. $role->getValue('rol_name'). '</a>
            </div>
            <div class="float-right text-right">');
                // send a mail to all role members
                if($gCurrentUser->hasRightSendMailToRole($rolId) && $gSettingsManager->getBool('enable_mail_module'))
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('rol_id' => $rolId)).'">'.
                        '<i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'"></i></a>');
                }

                // show link to export vCard if user is allowed to see members and the role has members
                if($row['num_members'] > 0 || $row['num_leader'] > 0)
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php', array('mode' => '8', 'rol_id' => $rolId)).'">'.
                        '<i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', array($role->getValue('rol_name'))).'"></i></a>');
                }

                // link to assign or remove members if you are allowed to do it
                if($role->allowedToAssignMembers($gCurrentUser))
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php', array('rol_id' => $rolId)).'">'.
                        '<i class="fas fa-user-plus" data-toggle="tooltip" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'"></i></a>');
                }

                // edit roles of you are allowed to assign roles
                if($gCurrentUser->manageRoles())
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php', array('rol_id' => $rolId)).'">'.
                        '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('ROL_EDIT_ROLE').'"></i></a>');
                }
            $page->addHtml('</div>
        </div>
        <div class="card-body" id="admRoleDetails'.$rolId.'">
            <h5 class="card-title">'. $role->getValue('rol_name'). '</h5>');

            if(strlen($role->getValue('rol_description')) > 0)
            {
                $page->addHtml('<div class="card-text">' . $role->getValue('rol_description') . '</div>');
                //$form->addStaticControl('list_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'));
            }

            // create a static form
            $form = new HtmlForm('lists_static_form');

            if(strlen($role->getValue('rol_start_date')) > 0)
            {
                $form->addStaticControl('list_date_from_to', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))));
            }

            if($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
            {
                if($role->getValue('rol_weekday') > 0)
                {
                    $html = DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                }
                if(strlen($role->getValue('rol_start_time')) > 0)
                {
                    $html = $gL10n->get('LST_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
                }
                $form->addStaticControl('list_date', $gL10n->get('DAT_DATE'), $html);
            }

            // Meeting point
            if(strlen($role->getValue('rol_location')) > 0)
            {
                $form->addStaticControl('list_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'));
            }

            // add count of participants to role
            $html = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId)). '">'.$row['num_members'].'</a>';

            if($role->getValue('rol_max_members') > 0)
            {
                $html .= '&nbsp;'.$gL10n->get('LST_MAX', array((int) $role->getValue('rol_max_members')));
            }
            if($gCurrentUser->hasRightViewFormerRolesMembers($rolId) && $getActiveRole && $row['num_former'] > 0)
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

                $html .= '&nbsp;&nbsp;(<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId, 'show_former_members' => '1')).'">'.$row['num_former'].' '.$textFormerMembers.'</a>) ';
            }
            $form->addStaticControl('list_participants', $gL10n->get('SYS_PARTICIPANTS'), $html);

            // Leader of role
            if($row['num_leader'] > 0)
            {
                $form->addStaticControl('list_leader', $gL10n->get('SYS_LEADERS'), $row['num_leader']);
            }

            // Member fee
            if(strlen($role->getValue('rol_cost')) > 0)
            {
                $form->addStaticControl('list_contribution', $gL10n->get('SYS_CONTRIBUTION'), (float) $role->getValue('rol_cost').' '.$gSettingsManager->getString('system_currency'));
            }

            // Contributory period
            if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
            {
                $form->addStaticControl('list_cost_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), TableRoles::getCostPeriods($role->getValue('rol_cost_period')));
            }

            $page->addHtml($form->show());
            $page->addHtml('
            <a class="btn btn-primary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId)) . '">' . $gL10n->get('SYS_SHOW_MEMBER_LIST') . '</a>
        </div>
    </div>');
}

if($listsResult['numResults'] > 0)
{
    $page->addHtml('</div>');
}

// If necessary show links to navigate to next and previous recordsets of the query
$baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/lists/lists.php', array('cat_id' => $getCatId, 'active_role' => (int) $getActiveRole));
$page->addHtml(admFuncGeneratePagination($baseUrl, $listsResult['totalCount'], $gSettingsManager->getInt('lists_roles_per_page'), $getStart));

$page->show();