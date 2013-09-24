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

// Navigation faengt hier im Modul an
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

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


// Html-Kopf ausgeben
if($lists->getActiveRole())
{
    $gLayout['title']  = $lists->getHeadline();
}
else
{
    $gLayout['title']  = $gL10n->get('LST_INACTIVE_ROLES');
}

if($lists->getCatId() > 0)
{
    $category = new TableCategory($gDb, $lists->getCatId());
    $gLayout['title'] .= ' - '.$category->getValue('cat_name');
}

$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() {
			$(".admSelectRoleList").change(function () {
				elementId = $(this).attr("id");
				roleId    = elementId.substr(elementId.search(/_/)+1);

				if($(this).val() == "mylist") {
					self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + roleId + "&active_role='.$lists->getActiveRole().'";
				}
				else {
					self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + $(this).val() + "&rol_id=" + roleId;
				}
			})
        });
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '<div id="lists_overview">
<h1 class="moduleHeadline">'. $gLayout['title']. '</h1>';

// Kategorienauswahlbox soll angezeigt werden oder der User darf neue Rollen anlegen
if($lists->getCategorySelection() == 1 || $gCurrentUser->assignRoles())
{
	// create module menu
	$ListsMenu = new ModuleMenu('admMenuLists');

	if($gCurrentUser->assignRoles())
	{
		// show link to create new role
		$ListsMenu->addItem('admMenuItemNewRole', $g_root_path.'/adm_program/administration/roles/roles_new.php', 
							$gL10n->get('SYS_CREATE_ROLE'), 'add.png');
	}
	
	// show selectbox with all roles categories
	$ListsMenu->addCategoryItem('admMenuItemCategory', 'ROL', $lists->getCatId(), 'lists.php?category-selection='. $lists->getCategorySelection(). '&active_role='.$lists->getActiveRole().'&cat_id=', 
								$gL10n->get('SYS_CATEGORY'), $gCurrentUser->assignRoles());

	if($gCurrentUser->isWebmaster())
	{
		// show link to system preferences of roles
		$ListsMenu->addItem('admMenuItemPreferencesLists', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=LST_LISTS', 
							$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
	}

	$ListsMenu->show();
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

foreach($listsResult as $row)
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
                echo $gL10n->get('LST_CATEGORY_NO_LISTS');
            }
            echo '</div></div><br />';
        }
        echo '<div class="formLayout">
            <div class="formHead">'. $role->getValue('cat_name'). '</div>
            <div class="formBody">';
        $previousCategoryId = $role->getValue('cat_id');
        $countCategoryEntries = 0;
    }

    if($countCategoryEntries > 0)
    {
        echo '<hr />';
    }
    echo '
    <div>
        <div style="float: left;">';
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
            echo '<a class="iconLink" href="javascript:showHideBlock(\'admRoleDetails'.$role->getValue('rol_id').'\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')">
                <img id="admRoleDetails'.$role->getValue('rol_id').'Image"  src="'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></a>';

            // show link if user is allowed to see members and the role has members
            if($row['num_members'] > 0 || $row['num_leader'] > 0)
            {
                echo '<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '">'. $role->getValue('rol_name'). '</a>';
            }
            else
            {
                echo '<strong>'. $role->getValue('rol_name'). '</strong>';
            }

            //Mail an Rolle schicken
            if($gCurrentUser->mailRole($role->getValue('rol_id')) && $gPreferences['enable_mail_module'] == 1)
            {
                echo '
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$role->getValue('rol_id').'"><img
                    src="'. THEME_PATH. '/icons/email.png"  alt="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>';
            }

            // edit roles of you are allowed to assign roles
            if($gCurrentUser->assignRoles())
            {
                echo '
                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
                    src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('SYS_SETTINGS').'" title="'.$gL10n->get('SYS_SETTINGS').'" /></a>';
            }

            // link to assign or remove members if you are allowed to do it
            if($role->allowedToAssignMembers($gCurrentUser))
            {
                echo '
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img 
                    src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>';
            }
        echo '</div>
        <div style="text-align: right;">';
            // show combobox with lists if user is allowed to see members and the role has members
            if($row['num_members'] > 0 || $row['num_leader'] > 0)
            {
                echo '
                <select class="admSelectRoleList" id="admSelectRoleList_'.$role->getValue('rol_id').'">
                    <option value="" selected="selected">'.$gL10n->get('LST_SHOW_LISTS').' ...</option>';
                    
                    // alle globalen Listenkonfigurationen auflisten
                    $list_global_flag = '';
                    
                    foreach($listConfigurations as $rowConfigurations)
                    {
                        if($list_global_flag != $rowConfigurations['lst_global'])
                        {
                            if($rowConfigurations['lst_global'] == 0)
                            {
                                echo '<optgroup label="'.$gL10n->get('LST_YOUR_LISTS').'">';
                            }
                            else
                            {
                                if($list_global_flag > 0)
                                {
                                    echo '</optgroup>';
                                }
                                echo '<optgroup label="'.$gL10n->get('LST_GENERAL_LISTS').'">';
                            }
                            $list_global_flag = $rowConfigurations['lst_global'];
                        }
                        echo '<option value="'.$rowConfigurations['lst_id'].'">'.$rowConfigurations['lst_name'].'</option>';
                    }
                    
                    // Link zu den eigenen Listen setzen
                    echo '</optgroup>
                    <optgroup label="'.$gL10n->get('LST_CONFIGURATION').'">
                        <option value="mylist">'.$gL10n->get('LST_CREATE_OWN_LIST').'</option>
                    </optgroup>
                </select>';
            }
            else
            {
                echo '&nbsp;';
            }
        echo '</div>
    </div>
    
    <ul id="admRoleDetails'.$role->getValue('rol_id').'" ';
        if($gPreferences['lists_hide_overview_details']==1)
        {
            echo ' style="display: none;" '; 
        }
        echo ' class="formFieldList">';
        if(strlen($role->getValue('rol_description')) > 0)
        {
            echo '
            <li>
                <dl>
                    <dt>'.$gL10n->get('SYS_DESCRIPTION').':</dt>
                    <dd>'.$role->getValue('rol_description').'</dd>
                </dl>
            </li>';
        }

        if(strlen($role->getValue('rol_start_date')) > 0)
        {
            echo '
            <li>
                <dl>
                    <dt>'.$gL10n->get('SYS_PERIOD').':</dt>
                    <dd>'.$gL10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $gPreferences['system_date']), $role->getValue('rol_end_date', $gPreferences['system_date'])).'</dd>
                </dl>
            </li>';
        }
        if($role->getValue('rol_weekday') > 0
        || strlen($role->getValue('rol_start_time')) > 0 )
        {
            echo '
            <li>
                <dl>
                    <dt>'.$gL10n->get('DAT_DATE').':</dt>
                    <dd>'; 
                        if($role->getValue('rol_weekday') > 0)
                        {
                            echo DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                        }
                        if(strlen($role->getValue('rol_start_time')) > 0)
                        {
                            echo $gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time']));
                        }
                    echo '</dd>
                </dl>
            </li>';
        }
        //Treffpunkt
        if(strlen($role->getValue('rol_location')) > 0)
        {
            echo '
            <li>
                <dl>
                    <dt>'.$gL10n->get('SYS_LOCATION').':</dt>
                    <dd>'.$role->getValue('rol_location').'</dd>
                </dl>
            </li>';
        }
        //Teinehmer
        echo '
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_PARTICIPANTS').':</dt>
                <dd>'.$row['num_members'];
                    if($role->getValue('rol_max_members') > 0)
                    {
                        echo '&nbsp;'.$gL10n->get('LST_MAX', $role->getValue('rol_max_members'));
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
                        
                        echo '&nbsp;&nbsp;(<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '&amp;show_members=1">'.$row['num_former'].' '.$textFormerMembers.'</a>) ';
                    }
                echo '</dd>
            </dl>
        </li>';

        //Leiter
        if($row['num_leader']>0)
        {
            echo '
            <li>
                <dl>
                    <dt>'.$gL10n->get('SYS_LEADER').':</dt>
                    <dd>'.$row['num_leader'].'</dd>
                </dl>
            </li>';
        }

        //Beitrag
        if(strlen($role->getValue('rol_cost')) > 0)
        {
            echo '
            <li>
                <dl>
                    <dt>'.$gL10n->get('SYS_CONTRIBUTION').':</dt>
                    <dd>'.$role->getValue('rol_cost').' '.$gPreferences['system_currency'].'</dd>
                </dl>
            </li>';
        }

        //Beitragszeitraum
        if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
        {
            echo'<li>
                <dl>
                    <dt>'.$gL10n->get('SYS_CONTRIBUTION_PERIOD').':</dt>
                    <dd>'.$role->getCostPeriods($role->getValue('rol_cost_period')).'</dd>
                </dl>
            </li>';
        }

    echo '</ul>';
    $countCategoryEntries++;  
}

// show message if no role was found
if($countCategoryEntries == 0)
{
    echo $gL10n->get('LST_CATEGORY_NO_LISTS');
}
else
{
    echo '</div></div>';

    // If neccessary show links to navigate to next and previous recordsets of the query
    $base_url = $g_root_path.'/adm_program/modules/lists/lists.php?cat_id='. $lists->getCatId(). '&category-selection='. $lists->getCategorySelection(). '&active_role='.$lists->getActiveRole();
    echo admFuncGeneratePagination($base_url, $numberOfRoles, $gPreferences['lists_roles_per_page'], $getStart, TRUE);
}

echo '</div>';
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>