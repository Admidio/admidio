<?php
/******************************************************************************
 * Show a list of all list roles
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * start    : Position of query recordset where the visual output should start
 * cat_id   : show only roles of this category id, if id is not set than show all roles
 * category-selection: 1 - (Default) Anzeige der Combobox mit den verschiedenen Rollen-Kategorien
 *                     0 - Combobox nicht anzeigen
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - inaktive Rollen auflisten
 *
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['mylist_request']);

//New Modulelist object
$lists = new ModuleLists();

//get Number of roles
$numberOfRoles = $lists->getDataSetCount();

if($numberOfRoles == 0)
{
    if($gValidLogin == true)
    {
        // wenn User eingeloggt, dann Meldung, falls doch keine Rollen zur Verfuegung stehen
        if($lists->getActiveRole() == 0)
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

// set headline
if($lists->getActiveRole())
{
    $headline = $lists->getHeadline();
}
else
{
    $headline = $gL10n->get('LST_INACTIVE_ROLES');
}

if($lists->getCatId() > 0)
{
    $category = new TableCategory($gDb, $lists->getCatId());
    $headline .= ' - '.$category->getValue('cat_name');
}

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage();

$page->addJavascript('
	$(".admSelectBoxListSelection").change(function () {
		elementId = $(this).attr("id");
		roleId    = elementId.substr(elementId.search(/_/)+1);

		if($(this).val() == "mylist") {
			self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + roleId + "&active_role='.$lists->getActiveRole().'";
		}
		else {
			self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + $(this).val() + "&rol_id=" + roleId;
		}
	})', true);

// add headline and title of module
$page->addHtml('<div id="lists_overview">');
$page->addHeadline($headline);

// Kategorienauswahlbox soll angezeigt werden oder der User darf neue Rollen anlegen
if($lists->getCategorySelection() == 1 || $gCurrentUser->manageRoles())
{
	// create module menu
	$ListsMenu = new ModuleMenu('admMenuLists');

	if($gCurrentUser->manageRoles())
	{
		// show link to create new role
		$ListsMenu->addItem('admMenuItemNewRole', $g_root_path.'/adm_program/administration/roles/roles_new.php', 
							$gL10n->get('SYS_CREATE_ROLE'), 'add.png');
	}
	
	// show selectbox with all roles categories
	$ListsMenu->addCategoryItem('admMenuItemCategory', 'ROL', $lists->getCatId(), 'lists.php?category-selection='. $lists->getCategorySelection(). '&active_role='.$lists->getActiveRole().'&cat_id=', 
								$gL10n->get('SYS_CATEGORY'), $gCurrentUser->manageRoles());

	if($gCurrentUser->isWebmaster())
	{
		// show link to system preferences of roles
		$ListsMenu->addItem('admMenuItemPreferencesLists', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=LST_LISTS', 
							$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
	}

	$page->addHtml($ListsMenu->show(false));
}

$previousCategoryId   = 0;
$countCategoryEntries = 0;

//Get Lists
$getStart = $lists->getStartElement();
$listsResult = $lists->getDataSet($getStart);

//Get list configurations
$listConfigurations = $lists->getListConfigurations();

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
        if($previousCategoryId!=0)
        {
            if($countCategoryEntries == 0)
            {
                $page->addHtml($gL10n->get('LST_CATEGORY_NO_LISTS'));
            }
            $page->addHtml('</div></div>');
        }
        $page->addHtml('<div class="admBoxLayout">
            <div class="admBoxHead">'. $role->getValue('cat_name'). '</div>
            <div class="admBoxBody">');
        $previousCategoryId = $role->getValue('cat_id');
        $countCategoryEntries = 0;
    }

    if($countCategoryEntries > 0)
    {
        $page->addHtml('<hr />');
    }
    $page->addHtml('
    <div class="admListsItem">');
        //Dreieck zum ein und ausblenden der Details
        if($gPreferences['lists_hide_overview_details']==1)
        {
            $icon = THEME_PATH. '/icons/triangle_close.gif';
            $iconText = $gL10n->get('SYS_FADE_IN');
        }
        else
        {
            $icon = THEME_PATH. '/icons/triangle_open.gif';
            $iconText = $gL10n->get('SYS_HIDE');
        }
        $page->addHtml('<a class="admIconLink" href="javascript:showHideBlock(\'admRoleDetails'.$role->getValue('rol_id').'\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')">
            <img id="admRoleDetails'.$role->getValue('rol_id').'Image"  src="'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></a>');

        // show link if user is allowed to see members and the role has members
        if($row['num_members'] > 0 || $row['num_leader'] > 0)
        {
            $page->addHtml('<a class="admBigFont admListsLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '">'. $role->getValue('rol_name'). '</a>');
        }
        else
        {
            $page->addHtml('<strong class="admBigFont admListsLink">'. $role->getValue('rol_name'). '</strong>');
        }
		
		// show link to export vCard if user is allowed to see members and the role has members
        if($row['num_members'] > 0 || $row['num_leader'] > 0)
        {
            $page->addHtml('<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile_function.php?mode=8&amp;rol_id='. $role->getValue('rol_id').'"><img
                            src="'. THEME_PATH. '/icons/vcard.png"
                            alt="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $role->getValue('rol_name')).'"
                            title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $role->getValue('rol_name')).'"/></a>');
        }

        //Mail an Rolle schicken
        if($gCurrentUser->mailRole($role->getValue('rol_id')) && $gPreferences['enable_mail_module'] == 1)
        {
            $page->addHtml('
            <a class="admIconLink" href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$role->getValue('rol_id').'"><img
                src="'. THEME_PATH. '/icons/email.png"  alt="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>');
        }

        // edit roles of you are allowed to assign roles
        if($gCurrentUser->manageRoles())
        {
            $page->addHtml('
            <a class="admIconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
                src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('SYS_SETTINGS').'" title="'.$gL10n->get('SYS_SETTINGS').'" /></a>');
        }

        // link to assign or remove members if you are allowed to do it
        if($role->allowedToAssignMembers($gCurrentUser))
        {
            $page->addHtml('
            <a class="admIconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img 
                src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>');
        }
    $page->addHtml('</div>
    
    <div id="admRoleDetails'.$role->getValue('rol_id').'" ');
        if($gPreferences['lists_hide_overview_details']==1)
        {
            $page->addHtml(' style="display: none;" '); 
        }
        $page->addHtml(' class="admFieldViewList">');

        // show combobox with lists if user is allowed to see members and the role has members
        if($row['num_members'] > 0 || $row['num_leader'] > 0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('LST_SHOW_LIST').':</div>
                <div class="admFieldElement">
                    <select class="admSelectBoxSmall admSelectBoxListSelection" id="admSelectRoleList_'.$role->getValue('rol_id').'">
                        <option value="" selected="selected">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>');
                        
                        // alle globalen Listenkonfigurationen auflisten
                        $list_global_flag = '';
                        
                        foreach($listConfigurations as $rowConfigurations)
                        {
                            if($list_global_flag != $rowConfigurations['lst_global'])
                            {
                                if($rowConfigurations['lst_global'] == 0)
                                {
                                    $page->addHtml('<optgroup label="'.$gL10n->get('LST_YOUR_LISTS').'">');
                                }
                                else
                                {
                                    if($list_global_flag > 0)
                                    {
                                        $page->addHtml('</optgroup>');
                                    }
                                    $page->addHtml('<optgroup label="'.$gL10n->get('LST_GENERAL_LISTS').'">');
                                }
                                $list_global_flag = $rowConfigurations['lst_global'];
                            }
                            $page->addHtml('<option value="'.$rowConfigurations['lst_id'].'">'.$rowConfigurations['lst_name'].'</option>');
                        }
                        
                        // Link zu den eigenen Listen setzen
                        $page->addHtml('</optgroup>
                        <optgroup label="'.$gL10n->get('LST_CONFIGURATION').'">
                            <option value="mylist">'.$gL10n->get('LST_CREATE_OWN_LIST').'</option>
                        </optgroup>
                    </select>
                </div>
            </div>');
        }

        if(strlen($role->getValue('rol_description')) > 0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('SYS_DESCRIPTION').':</div>
                <div class="admFieldElement">'.$role->getValue('rol_description').'</div>
            </div>');
        }

        if(strlen($role->getValue('rol_start_date')) > 0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('SYS_PERIOD').':</div>
                <div class="admFieldElement">'.$gL10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $gPreferences['system_date']), $role->getValue('rol_end_date', $gPreferences['system_date'])).'</div>
            </div>');
        }
        if($role->getValue('rol_weekday') > 0
        || strlen($role->getValue('rol_start_time')) > 0 )
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('DAT_DATE').':</div>
                <div class="admFieldElement">');
                        if($role->getValue('rol_weekday') > 0)
                        {
                            $page->addHtml(DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ');
                        }
                        if(strlen($role->getValue('rol_start_time')) > 0)
                        {
                            $page->addHtml($gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time'])));
                        }
                $page->addHtml('</div>
            </div>');
        }
        //Treffpunkt
        if(strlen($role->getValue('rol_location')) > 0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('SYS_LOCATION').':</div>
                <div class="admFieldElement">'.$role->getValue('rol_location').'</div>
            </div>');
        }
        //Teinehmer
        $page->addHtml('
        <div class="admFieldRow">
            <div class="admFieldLabel">'.$gL10n->get('SYS_PARTICIPANTS').':</div>
            <div class="admFieldElement">'.$row['num_members']);
                if($role->getValue('rol_max_members') > 0)
                {
                    $page->addHtml('&nbsp;'.$gL10n->get('LST_MAX', $role->getValue('rol_max_members')));
                }
                if($lists->getActiveRole() && $row['num_former'] > 0)
                {
                    // Anzahl Ehemaliger anzeigen
                    if($row['num_former'] == 1)
                    {
                        $textFormerMembers = $gL10n->get('SYS_FORMER');
                    }
                    else
                    {
                        $textFormerMembers = $gL10n->get('SYS_FORMER_PL');
                    }
                    
                    $page->addHtml('&nbsp;&nbsp;(<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '&amp;show_members=1">'.$row['num_former'].' '.$textFormerMembers.'</a>) ');
                }
            $page->addHtml('</div>
        </div>');

        //Leiter
        if($row['num_leader']>0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('SYS_LEADER').':</div>
                <div class="admFieldElement">'.$row['num_leader'].'</div>
            </div>');
        }

        //Beitrag
        if(strlen($role->getValue('rol_cost')) > 0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('SYS_CONTRIBUTION').':</div>
                <div class="admFieldElement">'.$role->getValue('rol_cost').' '.$gPreferences['system_currency'].'</div>
            </div>');
        }

        //Beitragszeitraum
        if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
        {
            $page->addHtml('
            <div class="admFieldRow">
                <div class="admFieldLabel">'.$gL10n->get('SYS_CONTRIBUTION_PERIOD').':</div>
                <div class="admFieldElement">'.$role->getCostPeriods($role->getValue('rol_cost_period')).'</div>
            </div>');
        }

    $page->addHtml('</div>');
    $countCategoryEntries++;  
}

// show message if no role was found
if($countCategoryEntries == 0)
{
    $page->addHtml($gL10n->get('LST_CATEGORY_NO_LISTS'));
}
else
{
    $page->addHtml('</div></div>');

    // If neccessary show links to navigate to next and previous recordsets of the query
    $base_url = $g_root_path.'/adm_program/modules/lists/lists.php?cat_id='. $lists->getCatId(). '&category-selection='. $lists->getCategorySelection(). '&active_role='.$lists->getActiveRole();
    $page->addHtml(admFuncGeneratePagination($base_url, $numberOfRoles, $gPreferences['lists_roles_per_page'], $getStart, TRUE));
}

$page->addHtml('</div>');
$page->show();

?>