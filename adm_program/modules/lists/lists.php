<?php
/******************************************************************************
 * Show a list of all list roles
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * start    : Position of query recordset where the visual output should start
 * cat_id   : show only roles of this category id, if id is not set than show all roles
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - inaktive Rollen auflisten
 *
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['mylist_request']);

// Initialize and check the parameters
$getStart      = admFuncVariableIsValid($_GET, 'start', 'numeric');
$getCatId      = admFuncVariableIsValid($_GET, 'cat_id', 'numeric');
$getActiveRole = admFuncVariableIsValid($_GET, 'active_role', 'boolean', array('defaultValue' => 1));

//New Modulelist object
$lists = new ModuleLists();
$lists->setParameter('cat_id', $getCatId);

// set headline
if($getActiveRole)
{
    $headline = $gL10n->get('LST_ACTIVE_ROLES');
}
else
{
    $headline = $gL10n->get('LST_INACTIVE_ROLES');
}

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

        if($(this).val() == "mylist") {
            self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + roleId + "&active_role='.$getActiveRole.'";
        }
        else {
            self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + $(this).val() + "&rol_id=" + roleId;
        }
    });', true);

// add headline and title of module
$page->addHtml('<div id="lists_overview">');

// get module menu
$ListsMenu = $page->getMenu();

if($gCurrentUser->manageRoles())
{
    // show link to create new role
    $ListsMenu->addItem('admMenuItemNewRole', $g_root_path.'/adm_program/modules/roles/roles_new.php',
                        $gL10n->get('SYS_CREATE_ROLE'), 'add.png');
}

if($gCurrentUser->manageRoles() && !$gCurrentUser->isWebmaster())
{
    // show link to maintain categories
    $ListsMenu->addItem('menu_item_maintain_categories', $g_root_path.'/adm_program/modules/categories/categories.php?type=ROL',
                        $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'application_view_tile.png');
}

$page->addJavascript('$("#cat_id").change(function() { $("#navbar_cat_id_form").submit();});', true);
$navbarForm = new HtmlForm('navbar_cat_id_form', $g_root_path.'/adm_program/modules/lists/lists.php?active_role='.$getActiveRole, $page, array('type' => 'navbar', 'setFocus' => false));
$navbarForm->addSelectBoxForCategories('cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', 'FILTER_CATEGORIES', array('defaultValue' => $getCatId));
$ListsMenu->addForm($navbarForm->show(false));

if($gCurrentUser->isWebmaster())
{
    // show link to system preferences of roles
    $ListsMenu->addItem('admMenuItemPreferencesLists', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=lists',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$previousCategoryId   = 0;

//Get Lists
$getStart      = $lists->getStartElement();
$listsResult   = $lists->getDataSet($getStart);
$numberOfRoles = $lists->getDataSetCount();

if($numberOfRoles == 0)
{
    if($gValidLogin == true)
    {
        // wenn User eingeloggt, dann Meldung, falls doch keine Rollen zur Verfuegung stehen
        if($getActiveRole == 0)
        {
            $gMessage->show($gL10n->get('LST_NO_ROLES_REMOVED'));
        }
        else
        {
            $gMessage->show($gL10n->get('LST_NO_RIGHTS_VIEW_LIST'));
        }
    }
    else
    {
        // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
        require_once('../../system/login_valid.php');
    }
}

//Get list configurations
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

// add list item for own list
$listConfigurations[] = array('mylist', $gL10n->get('LST_CREATE_OWN_LIST'), $gL10n->get('LST_CONFIGURATION'));

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

foreach($listsResult['recordset'] as $row)
{
    //Put data to Roleobject
    $role->setArray($row);

    //if category is different than previous, close old and open new one
    if($previousCategoryId != $role->getValue('cat_id'))
    {
        //close only if previous category is not 0
        if($previousCategoryId != 0)
        {
            $page->addHtml('</div></div></div>');
        }
        $page->addHtml('<div class="panel panel-primary">
            <div class="panel-heading">'. $role->getValue('cat_name'). '</div>
            <div class="panel-body">
                <div class="panel-group" id="accordion_'.$role->getValue('cat_id').'">');
        $previousCategoryId = $role->getValue('cat_id');
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
                    <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/messages/messages_write.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'. THEME_PATH. '/icons/email.png"  alt="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>&nbsp;');
                }

                // show link to export vCard if user is allowed to see members and the role has members
                if($row['num_members'] > 0 || $row['num_leader'] > 0)
                {
                    $page->addHtml('<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/profile/profile_function.php?mode=8&amp;rol_id='. $role->getValue('rol_id').'"><img
                                    src="'. THEME_PATH. '/icons/vcard.png"
                                    alt="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $role->getValue('rol_name')).'"
                                    title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $role->getValue('rol_name')).'" /></a>');
                }

                // link to assign or remove members if you are allowed to do it
                if($role->allowedToAssignMembers($gCurrentUser))
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>');
                }

                // edit roles of you are allowed to assign roles
                if($gCurrentUser->manageRoles())
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('ROL_EDIT_ROLE').'" title="'.$gL10n->get('ROL_EDIT_ROLE').'" /></a>');
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

                //Treffpunkt
                if(strlen($role->getValue('rol_location')) > 0)
                {
                    $form->addStaticControl('list_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'));
                }

                // add count of participants to role
                $html = $row['num_members'];
                if($role->getValue('rol_max_members') > 0)
                {
                    $html .= '&nbsp;'.$gL10n->get('LST_MAX', $role->getValue('rol_max_members'));
                }
                if($lists->getActiveRole() && $row['num_former'] > 0)
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

                    $html .= '&nbsp;&nbsp;(<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '&amp;show_members=1">'.$row['num_former'].' '.$textFormerMembers.'</a>) ';
                }
                $form->addStaticControl('list_participants', $gL10n->get('SYS_PARTICIPANTS'), $html);

                //Leiter
                if($row['num_leader']>0)
                {
                    $form->addStaticControl('list_leader', $gL10n->get('SYS_LEADER'), $row['num_leader']);
                }

                //Beitrag
                if(strlen($role->getValue('rol_cost')) > 0)
                {
                    $form->addStaticControl('list_contribution', $gL10n->get('SYS_CONTRIBUTION'), $role->getValue('rol_cost').' '.$gPreferences['system_currency']);
                }

                //Beitragszeitraum
                if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                {
                    $form->addStaticControl('list_cost_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), $role->getCostPeriods($role->getValue('rol_cost_period')));
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
$base_url = $g_root_path.'/adm_program/modules/lists/lists.php?cat_id='.$getCatId.'&active_role='.$getActiveRole;
$page->addHtml(admFuncGeneratePagination($base_url, $numberOfRoles, $gPreferences['lists_roles_per_page'], $getStart, true));

$page->addHtml('</div>');
$page->show();
