<?php
/******************************************************************************
 * Create and edit roles
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * rol_id: ID of role, that should be edited
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);

// Initialize local parameters
$showSystemCategory = false;

// nur Moderatoren duerfen Rollen anlegen und verwalten
if(!$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if($getRoleId > 0)
{
    $headline = $gL10n->get('ROL_EDIT_ROLE');
}
else
{
    $headline = $gL10n->get('SYS_CREATE_ROLE');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

if($getRoleId > 0)
{
    $role->readDataById($getRoleId);

    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id')
    && $role->getValue('cat_org_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
    if($role->getValue('rol_webmaster') == 1
    && $gCurrentUser->isWebmaster() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

	// hidden roles can also see hidden categories
	if($role->getValue('cat_system') == 1)
	{
		$showSystemCategory = true;
	}
}
else
{
    $role->setValue('rol_this_list_view', '1');
    $role->setValue('rol_mail_this_role', '2');
}

if(isset($_SESSION['roles_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$role->setArray($_SESSION['roles_request']);
    unset($_SESSION['roles_request']);
}

// holt eine Liste der ausgewaehlten abhaengigen Rolen
$childRoles = RoleDependency::getChildRoles($gDb,$getRoleId);

// Alle Rollen auflisten, die der Benutzer sehen darf
$sql = 'SELECT *
          FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
         WHERE rol_valid   = 1
           AND rol_visible = 1
           AND rol_cat_id  = cat_id
           AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY rol_name ';
$allRoles = $gDb->query($sql);

$childRoleObjects = array();

// create html page object
$page = new HtmlPage();

$page->addJavascript('
    $("#rol_assign_roles").change(function(){markRoleRight("rol_assign_roles", "rol_all_lists_view", true);});
    $("#rol_all_lists_view").change(function(){markRoleRight(\'rol_all_lists_view\', \'rol_assign_roles\', false);});
', true);

$page->addJavascript('
        // Rollenabhaengigkeiten markieren
        function hinzufuegen()
        {
            var child_roles = document.getElementById("ChildRoles");
            var all_roles   = document.getElementById("AllRoles");

            if(all_roles.selectedIndex >= 0)
            {
                NeuerEintrag = new Option(all_roles.options[all_roles.selectedIndex].text, all_roles.options[all_roles.selectedIndex].value, false, true);
                all_roles.options[all_roles.selectedIndex] = null;
                child_roles.options[child_roles.length] = NeuerEintrag;
            }
        }

        function entfernen()
        {
            var child_roles = document.getElementById("ChildRoles");
            var all_roles   = document.getElementById("AllRoles");

            if(child_roles.selectedIndex >= 0)
            {
                NeuerEintrag = new Option(child_roles.options[child_roles.selectedIndex].text, child_roles.options[child_roles.selectedIndex].value, false, true);
                child_roles.options[child_roles.selectedIndex] = null;
                all_roles.options[all_roles.length] = NeuerEintrag;
            }
        }

        function absenden()
        {
            var child_roles = document.getElementById("ChildRoles");

            for (var i = 0; i < child_roles.options.length; i++)
            {
                child_roles.options[i].selected = true;
            }

            form.submit();
        }

        //Prüfe Mitgliederanzahl
        function checkMaxMemberCount(inputValue)
        {

            // Alle abhängigen Rollen werden für die Darstellung gesichert
            var child_roles = document.getElementById("ChildRoles");

            //Wenn eine Maximale Mitgliederzahl angeben wurde, duerfen keine Rollenabhaengigkeiten bestehen
            if(inputValue > 0)
            {
                // Die Box zum konfigurieren der Rollenabhängig wird ausgeblendet
                document.getElementById("dependancies_box").style.visibility = "hidden";
                document.getElementById("dependancies_box").style.display    = "none";

                // Alle Abhängigen Rollen werden markiert und auf unabhängig gesetzt
                for (var i = 0; i < child_roles.options.length; i++)
                {
                    child_roles.options[i].selected = true;
                }
                entfernen();

				jQueryAlert("ROL_SAVE_ROLES");

            }
            else
            {

                // Alle Abhängigen Rollen werden markiert und auf abhängig gesetzt
                for (var i = 0; i < child_roles.options.length; i++)
                {
                    child_roles.options[i].selected = true;
                }
                hinzufuegen();

                // Die Box zum konfigurieren der Rollenabhängigkeit wird wieder eingeblendet
                document.getElementById("dependancies_box").style.visibility = "visible";
                document.getElementById("dependancies_box").style.display    = "";


            }
        }

        // Rollenrechte markieren
        // Uebergaben:
        // srcRight  - ID des Rechts, welches das Ereignis ausloest
        // destRight - ID des Rechts, welches angepasst werden soll
        // checked   - true destRight wird auf checked gesetzt
        //             false destRight wird auf unchecked gesetzt
        function markRoleRight(srcRight, destRight, checked)
        {
            if(document.getElementById(srcRight).checked == true
            && checked == true)
            {
                document.getElementById(destRight).checked = true;
            }
            if(document.getElementById(srcRight).checked == false
            && checked == false)
            {
                document.getElementById(destRight).checked = false;
            }
        }');

// add headline and title of module
$page->addHeadline($headline);

// create module menu with back link
$rolesEditMenu = new HtmlNavbar('menu_roles_edit');
$rolesEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($rolesEditMenu->show(false));

// show form
$form = new HtmlForm('roles_edit_form', $g_root_path.'/adm_program/modules/roles/roles_function.php?rol_id='.$getRoleId.'&amp;mode=2', $page);
$form->openGroupBox('gb_name_category', $gL10n->get('SYS_NAME').' & '.$gL10n->get('SYS_CATEGORY'));
	if($role->getValue('rol_webmaster') == 1)
	{
        $form->addTextInput('rol_name', $gL10n->get('SYS_NAME'), $role->getValue('rol_name'), 100, FIELD_DISABLED);
    }
    else
    {
        $form->addTextInput('rol_name', $gL10n->get('SYS_NAME'), $role->getValue('rol_name'), 100, FIELD_MANDATORY);
    }
    $form->addMultilineTextInput('rol_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'), 2, 255);
    $form->addSelectBoxForCategories('rol_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ROL', 'EDIT_CATEGORIES', FIELD_MANDATORY, $role->getValue('rol_cat_id'));
$form->closeGroupBox();
$form->openGroupBox('gb_properties', $gL10n->get('SYS_PROPERTIES'));
    if($gPreferences['enable_mail_module'])
    {
    	$selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('ROL_ONLY_ROLE_MEMBERS'), 2 => $gL10n->get('ROL_ALL_MEMBERS'), 3 => $gL10n->get('ROL_ALL_GUESTS'));
        $form->addSelectBox('rol_mail_this_role', $gL10n->get('ROL_SEND_MAILS'), $selectBoxEntries, FIELD_DEFAULT, $role->getValue('rol_mail_this_role'), false, false, array('ROL_RIGHT_MAIL_THIS_ROLE_DESC', $gL10n->get('ROL_RIGHT_MAIL_TO_ALL')));
    }
	$selectBoxEntries = array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('ROL_ONLY_ROLE_MEMBERS'), 2 => $gL10n->get('ROL_ALL_MEMBERS'));
    $form->addSelectBox('rol_this_list_view', $gL10n->get('ROL_SEE_ROLE_MEMBERSHIP'), $selectBoxEntries, FIELD_DEFAULT, $role->getValue('rol_this_list_view'), false, false, array('ROL_RIGHT_THIS_LIST_VIEW_DESC', $gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW')));
	$selectBoxEntries = array(0 => $gL10n->get('ROL_NO_ADDITIONAL_RIGHTS'), 1 => $gL10n->get('SYS_ASSIGN_MEMBERS'), 2 => $gL10n->get('SYS_EDIT_MEMBERS'), 3 => $gL10n->get('ROL_ASSIGN_EDIT_MEMBERS'));
    $form->addSelectBox('rol_leader_rights', $gL10n->get('SYS_LEADER'), $selectBoxEntries, FIELD_DEFAULT, $role->getValue('rol_leader_rights'), false, false, 'ROL_LEADER_RIGHTS_DESC');
    
	$selectBoxEntries = array(0 => $gL10n->get('ROL_SYSTEM_DEFAULT_LIST'));
	// SQL-Statement fuer alle Listenkonfigurationen vorbereiten, die angezeigt werdne sollen
	$sql = 'SELECT lst_id, lst_name FROM '. TBL_LISTS. '
		     WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id'). '
		       AND lst_global = 1
		       AND lst_name IS NOT NULL
		     ORDER BY lst_global ASC, lst_name ASC';
	$gDb->query($sql);
	
	while($row = $gDb->fetch_array())
	{
		$selectBoxEntries[$row['lst_id']] = $row['lst_name'];
	}
    $form->addSelectBox('rol_lst_id', $gL10n->get('ROL_DEFAULT_LIST'), $selectBoxEntries, FIELD_DEFAULT, $role->getValue('rol_lst_id'), true, false, 'ROL_DEFAULT_LIST_DESC');
    $form->addCheckbox('rol_default_registration', $gL10n->get('ROL_DEFAULT_REGISTRATION'), $role->getValue('rol_default_registration'), FIELD_DEFAULT, 'ROL_DEFAULT_REGISTRATION_DESC');
    $form->addTextInput('rol_max_members', $gL10n->get('SYS_MAX_PARTICIPANTS').'<br />('.$gL10n->get('ROL_WITHOUT_LEADER').')', $role->getValue('rol_max_members'), 3, FIELD_DEFAULT, 'number');
    $form->addTextInput('rol_cost', $gL10n->get('SYS_CONTRIBUTION').' '.$gPreferences['system_currency'], $role->getValue('rol_cost'), 6, FIELD_DEFAULT, 'text', null, null, null, 'form-control-small');
    $form->addSelectBox('rol_cost_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), $role->getCostPeriods(), FIELD_DEFAULT, $role->getValue('rol_cost_period'));
$form->closeGroupBox();
$form->openGroupBox('gb_authorization', $gL10n->get('SYS_AUTHORIZATION'));
	$form->addCheckbox('rol_assign_roles', $gL10n->get('ROL_RIGHT_ASSIGN_ROLES'), $role->getValue('rol_assign_roles'), FIELD_DEFAULT, 'ROL_RIGHT_ASSIGN_ROLES_DESC', null, 'roles.png');
	$form->addCheckbox('rol_all_lists_view', $gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW'), $role->getValue('rol_all_lists_view'), FIELD_DEFAULT, null, null, 'lists.png');
	$form->addCheckbox('rol_approve_users', $gL10n->get('ROL_RIGHT_APPROVE_USERS'), $role->getValue('rol_approve_users'), FIELD_DEFAULT, null, null, 'new_registrations.png');
	$form->addCheckbox('rol_edit_user', $gL10n->get('ROL_RIGHT_EDIT_USER'), $role->getValue('rol_edit_user'), FIELD_DEFAULT, 'ROL_RIGHT_EDIT_USER_DESC', null, 'group.png');
    if($gPreferences['enable_mail_module'] > 0)
    {
    	$form->addCheckbox('rol_mail_to_all', $gL10n->get('ROL_RIGHT_MAIL_TO_ALL'), $role->getValue('rol_mail_to_all'), FIELD_DEFAULT, null, null, 'email.png');
    }
	$form->addCheckbox('rol_profile', $gL10n->get('ROL_RIGHT_PROFILE'), $role->getValue('rol_profile'), FIELD_DEFAULT, null, null, 'profile.png');
    if($gPreferences['enable_announcements_module'] > 0)
    {
    	$form->addCheckbox('rol_announcements', $gL10n->get('ROL_RIGHT_ANNOUNCEMENTS'), $role->getValue('rol_announcements'), FIELD_DEFAULT, null, null, 'announcements.png');
    }
    if($gPreferences['enable_dates_module'] > 0)
    {
    	$form->addCheckbox('rol_dates', $gL10n->get('ROL_RIGHT_DATES'), $role->getValue('rol_dates'), FIELD_DEFAULT, null, null, 'dates.png');
    }
    if($gPreferences['enable_photo_module'] > 0)
    {
    	$form->addCheckbox('rol_photo', $gL10n->get('ROL_RIGHT_PHOTO'), $role->getValue('rol_photo'), FIELD_DEFAULT, null, null, 'photo.png');
    }
    if($gPreferences['enable_download_module'] > 0)
    {
    	$form->addCheckbox('rol_download', $gL10n->get('ROL_RIGHT_DOWNLOAD'), $role->getValue('rol_download'), FIELD_DEFAULT, null, null, 'download.png');
    }
    if($gPreferences['enable_guestbook_module'] > 0)
    {
    	$form->addCheckbox('rol_guestbook', $gL10n->get('ROL_RIGHT_GUESTBOOK'), $role->getValue('rol_guestbook'), FIELD_DEFAULT, null, null, 'guestbook.png');
    	// if not registered users can set comments than there is no need to set a role dependent right
        if($gPreferences['enable_gbook_comments4all'] == false)
        {
        	$form->addCheckbox('rol_guestbook_comments', $gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS'), $role->getValue('rol_guestbook_comments'), FIELD_DEFAULT, null, null, 'comment.png');
        }
    }
    if($gPreferences['enable_weblinks_module'] > 0)
    {
    	$form->addCheckbox('rol_weblinks', $gL10n->get('ROL_RIGHT_WEBLINKS'), $role->getValue('rol_weblinks'), FIELD_DEFAULT, null, null, 'weblinks.png');
    }
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
$form->addHtml(admFuncShowCreateChangeInfoById($role->getValue('rol_usr_id_create'), $role->getValue('rol_timestamp_create'), $role->getValue('rol_usr_id_change'), $role->getValue('rol_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

/*
        <div class="groupBox" id="admDatesBox">
            <div class="groupBoxHeadline" id="admDatesHead">
                <a class="iconShowHide" href="javascript:showHideBlock(\'admDatesBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
                	id="admDatesBodyImage" src="'.THEME_PATH.'/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('DAT_DATES').' / '.$gL10n->get('ROL_MEETINGS').'&nbsp;&nbsp;('.$gL10n->get('SYS_OPTIONAL').')
            </div>

            <div class="groupBoxBody" id="admDatesBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="rol_start_date">'.$gL10n->get('ROL_VALID_FROM').':</label></dt>
                            <dd>
                                <input type="text" id="rol_start_date" name="rol_start_date" size="10" maxlength="10" value="'.$role->getValue('rol_start_date').'" />
                                <a class="iconLink" id="anchor_date_from" href="javascript:calPopup.select(document.getElementById(\'rol_start_date\'),\'anchor_date_from\',\''.$gPreferences['system_date'].'\',\'rol_start_date\',\'rol_end_date\');"><img
                                	src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>
                                <label for="rol_end_date">'.$gL10n->get('SYS_DATE_TO').'</label>
                                <input type="text" id="rol_end_date" name="rol_end_date" size="10" maxlength="10" value="'.$role->getValue('rol_end_date').'" />
                                <a class="iconLink" id="anchor_date_to" href="javascript:calPopup.select(document.getElementById(\'rol_end_date\'),\'anchor_date_to\',\''.$gPreferences['system_date'].'\',\'rol_start_date\',\'rol_end_date\');"><img
                                	src="'.THEME_PATH.'/icons/calendar.png" alt="'.$gL10n->get('SYS_SHOW_CALENDAR').'" title="'.$gL10n->get('SYS_SHOW_CALENDAR').'" /></a>&nbsp;(Datum)
                                <span id="calendardiv" style="position: absolute; visibility: hidden;"></span>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="rol_start_time">'.$gL10n->get('SYS_TIME').':</label></dt>
                            <dd>
                                <input type="text" id="rol_start_time" name="rol_start_time" size="10" maxlength="10" value="'.$role->getValue('rol_start_time', $gPreferences['system_time']).'" />
                                <label for="rol_end_time">'.$gL10n->get('SYS_DATE_TO').'</label>
                                <input type="text" id="rol_end_time" name="rol_end_time" size="10" maxlength="10" value="'.$role->getValue('rol_end_time', $gPreferences['system_time']).'" />
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="rol_weekday">'.$gL10n->get('ROL_WEEKDAY').':</label></dt>
                            <dd>
                                <select size="1" id="rol_weekday" name="rol_weekday">';
								// add a default entry to the combobox
                                echo '<option value="0"';
                                if($role->getValue('rol_weekday') == 0)
                                {
                                    echo ' selected="selected" ';
                                }
                                echo '>--</option>';
                                
                                // now list all weekdays that are defined in the datetime class
                                foreach(DateTimeExtended::getWeekdays() as $roleWeekday => $dayName) 
                                {
                                    echo '<option value="'.$roleWeekday.'" ';
                                    if($role->getValue('rol_weekday') == $roleWeekday)
                                    {
                                        echo 'selected="selected"';
                                    }
                                    echo '>'.DateTimeExtended::getWeekdays($roleWeekday).'</option>';
                                }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="rol_location">'.$gL10n->get('SYS_LOCATION').':</label></dt>
                            <dd>
                                <input type="text" id="rol_location" name="rol_location" size="30" maxlength="30" value="'.$role->getValue('rol_location').'" />
                            </dd>
                        </dl>
                    </li>
                </ul>
            </div>
        </div>';
        if($role->getValue('rol_max_members') == 0)
        {
            echo '<div class="groupBox" id="admDependanciesBox">
                <div class="groupBoxHeadline" id="admDependanciesHead">
                    <a class="iconShowHide" href="javascript:showHideBlock(\'admDependanciesBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
                    id="admDependanciesBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('ROL_DEPENDENCIES').'&nbsp;&nbsp;('.$gL10n->get('SYS_OPTIONAL').')
                </div>

                <div class="groupBoxBody" id="admDependanciesBody">
                    <div style="margin-top: 6px;">';
                        $rolename_var = $gL10n->get('ROL_NEW_ROLE');
                        if($role->getValue('rol_name')!='')
                        {
                            $rolename_var = $gL10n->get('SYS_ROLE').' <b>'.$role->getValue('rol_name').'</b>';
                        }
                        echo '<p>'.$gL10n->get('ROL_ROLE_DEPENDENCIES', $rolename_var).'</p>

                        <div style="text-align: left; float: left;">
                            <div><img class="iconInformation" src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('ROL_INDEPENDENT').'" title="'.$gL10n->get('ROL_INDEPENDENT').'" />'.$gL10n->get('ROL_INDEPENDENT').'</div>
                            <div>
                                <select id="AllRoles" size="8" style="width: 200px;">';
                                    while($row = $gDb->fetch_object($allRoles))
                                    {
                                        if(in_array($row->rol_id,$childRoles)  )
                                        {
                                            $childRoleObjects[] = $row;
                                        }
                                        elseif ($row->rol_id != $getRoleId)
                                        {
                                            echo '<option value="'.$row->rol_id.'">'.$row->rol_name.'</option>';
                                        }
                                    }
                                echo '</select>
                            </div>
                        </div>
                        <div style="float: left;" class="verticalIconList">
                            <ul>
                                <li>
                                    <a class="iconLink" href="javascript:hinzufuegen()"><img
                                     src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_ADD_ROLE').'" title="'.$gL10n->get('SYS_ADD_ROLE').'" /></a>
                                </li>
                                <li>
                                    <a class="iconLink" href="javascript:entfernen()"><img 
                                    src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_REMOVE_ROLE').'" title="'.$gL10n->get('SYS_REMOVE_ROLE').'" /></a>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <div><img class="iconInformation" src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('ROL_DEPENDENT').'" title="'.$gL10n->get('ROL_DEPENDENT').'" />'.$gL10n->get('ROL_DEPENDENT').'</div>
                            <div>
                                <select id="ChildRoles" name="ChildRoles[]" size="8" multiple="multiple" style="width: 200px;">';
                                    foreach ($childRoleObjects as $childRoleObject)
                                    {
                                        echo '<option value="'.$childRoleObject->rol_id.'">'.$childRoleObject->rol_name.'</option>';
                                    }
                                echo '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

        // show informations about user who creates the recordset and changed it
        echo admFuncShowCreateChangeInfoById($role->getValue('rol_usr_id_create'), $role->getValue('rol_timestamp_create'), $role->getValue('rol_usr_id_change'), $role->getValue('rol_timestamp_change')).'
        
        <div class="formSubmit">
            <button id="btnSave" type="submit" onclick="absenden()">
                <img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />
                &nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
*/
?>
