<?php
/**
 ***********************************************************************************************
 * Show a list of all list roles
 *
 * @copyright 2004-2017 The Admidio Team
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
require_once('../../system/common.php');

// Initialize and check the parameters
$getStart      = admFuncVariableIsValid($_GET, 'start',       'int');
$getCatId      = admFuncVariableIsValid($_GET, 'cat_id',      'int');
$getActiveRole = admFuncVariableIsValid($_GET, 'active_role', 'bool', array('defaultValue' => true));

// set headline
if($getActiveRole)
{
    $headline = $gL10n->get('LST_ACTIVE_ROLES');
}
else
{
    $headline = $gL10n->get('LST_INACTIVE_ROLES');
}

// New Modulelist object
$lists = new ModuleLists();
$lists->setParameter('cat_id', $getCatId);
$lists->setParameter('active_role', $getActiveRole);

if($getCatId > 0)
{
    $category = new TableCategory($gDb, $getCatId);
    $headline .= ' - '.$category->getValue('cat_name');
}

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

if($gPreferences['lists_hide_overview_details'] == 0)
{
    $page->addJavascript('$(".collapse").collapse();', true);
}

$page->addJavascript('
    $(".panel-collapse select").change(function () {
        elementId = $(this).attr("id");
        roleId    = elementId.substr(elementId.search(/_/)+1);

        if ($(this).val() === "mylist") {
            self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + roleId + "&active_role='.(int) $getActiveRole.'";
        } else {
            self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + $(this).val() + "&rol_ids=" + roleId;
        }
    });', true);

// add headline and title of module
$page->addHtml('<div id="lists_overview">');

// get module menu
$ListsMenu = $page->getMenu();

if($gCurrentUser->manageRoles())
{
    // show link to create new role
    $ListsMenu->addItem('admMenuItemNewRole', ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php',
                        $gL10n->get('SYS_CREATE_ROLE'), 'add.png');
}

if($gCurrentUser->manageRoles() && !$gCurrentUser->isAdministrator())
{
    // show link to maintain categories
    $ListsMenu->addItem('menu_item_maintain_categories', ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php?type=ROL',
                        $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'application_view_tile.png');
}

$page->addJavascript('$("#cat_id").change(function() { $("#navbar_cat_id_form").submit(); });', true);
$navbarForm = new HtmlForm('navbar_cat_id_form', ADMIDIO_URL.FOLDER_MODULES.'/lists/lists.php?active_role='.(int) $getActiveRole, $page, array('type' => 'navbar', 'setFocus' => false));
$navbarForm->addSelectBoxForCategories('cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', 'FILTER_CATEGORIES', array('defaultValue' => $getCatId));
$ListsMenu->addForm($navbarForm->show(false));

if($gCurrentUser->isAdministrator())
{
    // show link to system preferences of roles
    $ListsMenu->addItem('admMenuItemPreferencesLists', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=lists',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$previousCategoryId = 0;

// Get Lists
$getStart      = $lists->getStartElement();
$listsResult   = $lists->getDataSet($getStart);
$numberOfRoles = $lists->getDataSetCount();

if($numberOfRoles === 0)
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
            $gMessage->show($gL10n->get('LST_NO_ROLES_REMOVED'));
            // => EXIT
        }
    }
    else
    {
        // forward to login page
        require_once('../../system/login_valid.php');
    }
}

// Get list configurations
$listConfigurations = $lists->getListConfigurations();

foreach($listConfigurations as &$rowConfigurations)
{
    if($rowConfigurations[2] == 0)
    {
        $rowConfigurations[2] = $gL10n->get('LST_YOUR_LISTS');
    }
    else
    {
        $rowConfigurations[2] = $gL10n->get('LST_GENERAL_LISTS');
    }
}
unset($rowConfigurations);

// add list item for own list
$listConfigurations[] = array('mylist', $gL10n->get('LST_CREATE_OWN_LIST'), $gL10n->get('LST_CONFIGURATION'));

// Create role object
$role = new TableRoles($gDb);

foreach($listsResult['recordset'] as $row)
{
    // Put data to Roleobject
    $role->setArray($row);

    // if category is different than previous, close old and open new one
    if($previousCategoryId !== (int) $role->getValue('cat_id'))
    {
        // close only if previous category is not 0
        if($previousCategoryId !== 0)
        {
            $page->addHtml('</div></div></div>');
        }
        $page->addHtml('<div class="panel panel-primary">
            <div class="panel-heading">'. $role->getValue('cat_name'). '</div>
            <div class="panel-body">
                <div class="panel-group" id="accordion_'.$role->getValue('cat_id').'">');
        $previousCategoryId = (int) $role->getValue('cat_id');
    }

    $page->addHtml('
    <div class="panel panel-default" id="role_details_panel_'.$role->getValue('rol_id').'">
        <div class="panel-heading">
            <div class="pull-left">
                <h4 class="panel-title">
                    <a data-toggle="collapse" data-parent="#accordion_'.$role->getValue('cat_id').'" href="#collapse_'.$role->getValue('rol_id').'">
                        '. $role->getValue('rol_name'). '</a></h4>
            </div>
            <div class="pull-right text-right">');
                // send a mail to all role members
                if($gCurrentUser->hasRightSendMailToRole($role->getValue('rol_id')) && $gPreferences['enable_mail_module'] == 1)
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'. THEME_URL. '/icons/email.png"  alt="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>&nbsp;');
                }

                // show link to export vCard if user is allowed to see members and the role has members
                if($row['num_members'] > 0 || $row['num_leader'] > 0)
                {
                    $page->addHtml('<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php?mode=8&amp;rol_id='. $role->getValue('rol_id').'"><img
                                    src="'. THEME_URL. '/icons/vcard.png"
                                    alt="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $role->getValue('rol_name')).'"
                                    title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $role->getValue('rol_name')).'" /></a>');
                }

                // link to assign or remove members if you are allowed to do it
                if($role->allowedToAssignMembers($gCurrentUser))
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'.THEME_URL.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>');
                }

                // edit roles of you are allowed to assign roles
                if($gCurrentUser->manageRoles())
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'.THEME_URL.'/icons/edit.png" alt="'.$gL10n->get('ROL_EDIT_ROLE').'" title="'.$gL10n->get('ROL_EDIT_ROLE').'" /></a>');
                }
            $page->addHtml('</div>
        </div>
        <div id="collapse_'.$role->getValue('rol_id').'" class="panel-collapse collapse">
            <div class="panel-body" id="admRoleDetails'.$role->getValue('rol_id').'">');
                // create a static form
                $form = new HtmlForm('lists_static_form', null);

                // show combobox with lists if user is allowed to see members and the role has members
                if($row['num_members'] > 0 || $row['num_leader'] > 0)
                {
                    $form->addSelectBox('admSelectRoleList_'.$role->getValue('rol_id'), $gL10n->get('LST_SHOW_LIST'), $listConfigurations, array('firstEntry' => $gL10n->get('LST_CHOOSE_LIST')));
                }

                if(strlen($role->getValue('rol_description')) > 0)
                {
                    $form->addStaticControl('list_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'));
                }

                if(strlen($role->getValue('rol_start_date')) > 0)
                {
                    $form->addStaticControl('list_date_from_to', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $gPreferences['system_date']), $role->getValue('rol_end_date', $gPreferences['system_date'])));
                }

                if($role->getValue('rol_weekday') > 0
                || strlen($role->getValue('rol_start_time')) > 0)
                {
                    if($role->getValue('rol_weekday') > 0)
                    {
                        $html = DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                    }
                    if(strlen($role->getValue('rol_start_time')) > 0)
                    {
                        $html = $gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time']));
                    }
                    $form->addStaticControl('list_date', $gL10n->get('DAT_DATE'), $html);
                }

                // Meeting point
                if(strlen($role->getValue('rol_location')) > 0)
                {
                    $form->addStaticControl('list_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'));
                }

                // add count of participants to role
                $html = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?mode=html&amp;rol_ids='. $role->getValue('rol_id'). '&amp;show_members=0">'.$row['num_members'].'</a>';

                if($role->getValue('rol_max_members') > 0)
                {
                    $html .= '&nbsp;'.$gL10n->get('LST_MAX', $role->getValue('rol_max_members'));
                }
                if($getActiveRole && $row['num_former'] > 0)
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

                    $html .= '&nbsp;&nbsp;(<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?mode=html&amp;rol_ids='. $role->getValue('rol_id'). '&amp;show_members=1">'.$row['num_former'].' '.$textFormerMembers.'</a>) ';
                }
                $form->addStaticControl('list_participants', $gL10n->get('SYS_PARTICIPANTS'), $html);

                // Leader of role
                if($row['num_leader']>0)
                {
                    $form->addStaticControl('list_leader', $gL10n->get('SYS_LEADERS'), $row['num_leader']);
                }

                // Member fee
                if(strlen($role->getValue('rol_cost')) > 0)
                {
                    $form->addStaticControl('list_contribution', $gL10n->get('SYS_CONTRIBUTION'), $role->getValue('rol_cost').' '.$gPreferences['system_currency']);
                }

                // Contributory period
                if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                {
                    $form->addStaticControl('list_cost_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), TableRoles::getCostPeriods($role->getValue('rol_cost_period')));
                }

                $page->addHtml($form->show(false));
            $page->addHtml('</div>
        </div>
    </div>');
}

if($listsResult['numResults'] > 0)
{
    $page->addHtml('</div></div></div>');
}

// If necessary show links to navigate to next and previous recordsets of the query
$base_url = ADMIDIO_URL.FOLDER_MODULES.'/lists/lists.php?cat_id='.$getCatId.'&active_role='.(int) $getActiveRole;
$page->addHtml(admFuncGeneratePagination($base_url, $numberOfRoles, (int) $gPreferences['lists_roles_per_page'], $getStart, true));

$page->addHtml('</div>');
$page->show();
