<?php
/******************************************************************************
 * Create and edit roles
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/role_dependency.php');
require_once('../../system/classes/table_roles.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);

// nur Moderatoren duerfen Rollen anlegen und verwalten
if(!$gCurrentUser->assignRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

if($getRoleId > 0)
{
    $role->readData($getRoleId);

    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id')
    && $role->getValue('cat_org_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
    if($role->getValue('rol_name')    == $gL10n->get('SYS_WEBMASTER')
    && $gCurrentUser->isWebmaster() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
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

// Html-Kopf ausgeben
if($getRoleId > 0)
{
    $gLayout['title'] = $gL10n->get('ROL_EDIT_ROLE');
}
else
{
    $gLayout['title'] = $gL10n->get('SYS_CREATE_ROLE');
    $role->setValue('rol_this_list_view', '1');
    $role->setValue('rol_mail_this_role', '2');
}
$gLayout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
    <link rel="stylesheet" href="'.THEME_PATH.'/css/calendar.css" type="text/css" />
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#rol_name").focus(); ';
            // Bloecke anzeigen/verstecken
            if($getRoleId > 0)
            {
                if(strlen($role->getValue('rol_start_date')) == 0
                && strlen($role->getValue('rol_end_date')) == 0
                && strlen($role->getValue('rol_start_time')) == 0
                && strlen($role->getValue('rol_end_time')) == 0
                && $role->getValue('rol_weekday') == 0
                && strlen($role->getValue('rol_location')) == 0)
                {
                    $gLayout['header'] .= 'showHideBlock("admDatesBody", "'.$gL10n->get('SYS_FADE_IN').'", "'.$gL10n->get('SYS_HIDE').'"); ';
                }
                if(count($childRoles) == 0)
                {
                    $gLayout['header'] .= 'showHideBlock("admDependanciesBody", "'.$gL10n->get('SYS_FADE_IN').'", "'.$gL10n->get('SYS_HIDE').'"); ';
                }
            }
            $gLayout['header'] .= '
        }); 

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
        }

        // Calendarobjekt fuer das Popup anlegen
        var calPopup = new CalendarPopup("calendardiv");
        calPopup.setCssPrefix("calendar");
    //--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form id="formRole" action="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$getRoleId.'&amp;mode=2" method="post">
<div class="formLayout" id="edit_roles_form">
    <div class="formHead">'.$gLayout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="rol_name">'.$gL10n->get('SYS_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="rol_name" name="rol_name" ';
                        // bei bestimmte Rollen darf der Name nicht geaendert werden
                        if($role->getValue('rol_name') == $gL10n->get('SYS_WEBMASTER'))
                        {
                            echo ' readonly="readonly" ';
                        }
                        echo ' style="width: 320px;" maxlength="50" value="'. $role->getValue('rol_name'). '" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="rol_description">'.$gL10n->get('SYS_DESCRIPTION').':</label></dt>
                    <dd>
                        <input type="text" id="rol_description" name="rol_description" style="width: 320px;" maxlength="255" value="'. $role->getValue('rol_description'). '" />
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="rol_cat_id">'.$gL10n->get('SYS_CATEGORY').':</label></dt>
                    <dd>
						'.FormElements::generateCategorySelectBox('ROL', $role->getValue('rol_cat_id'), 'rol_cat_id').'
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <div class="groupBox" id="admPropertiesBox">
            <div class="groupBoxHeadline" id="admPropertiesHead">
                <a class="iconShowHide" href="javascript:showHideBlock(\'admPropertiesBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
                id="admPropertiesBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_PROPERTIES').'
            </div>

            <div class="groupBoxBody" id="admPropertiesBody">
                <ul class="formFieldList">';
                    if($gPreferences['enable_mail_module'])
                    {
                        echo '
                        <li>
                            <dl>
                                <dt><label for="rol_mail_this_role">'.$gL10n->get('ROL_SEND_MAILS').':</label></dt>
                                <dd>
                                    <select size="1" id="rol_mail_this_role" name="rol_mail_this_role">
                                        <option value="0" ';
                                            if($role->getValue('rol_mail_this_role') == 0)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$gL10n->get('SYS_NOBODY').'</option>
                                        <option value="1" ';
                                            if($role->getValue('rol_mail_this_role') == 1)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$gL10n->get('ROL_ONLY_ROLE_MEMBERS').'</option>
                                        <option value="2" ';
                                            if($role->getValue('rol_mail_this_role') == 2)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$gL10n->get('ROL_ALL_MEMBERS').'</option>
                                        <option value="3" ';
                                            if($role->getValue('rol_mail_this_role') == 3)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$gL10n->get('ROL_ALL_GUESTS').'</option>
                                    </select>
                                    <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ROL_RIGHT_MAIL_THIS_ROLE_DESC&amp;message_var1=ROL_RIGHT_MAIL_TO_ALL&amp;inline=true"><img 
                                        onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ROL_RIGHT_MAIL_THIS_ROLE_DESC&amp;message_var1=ROL_RIGHT_MAIL_TO_ALL\',this)" onmouseout="ajax_hideTooltip()"
                                        class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>	                                
                                </dd>
                            </dl>
                        </li>';
                    }
                    echo '
                    <li>
                        <dl>
                            <dt><label for="rol_this_list_view">'.$gL10n->get('ROL_SEE_ROLE_MEMBERSHIP').':</label></dt>
                            <dd>
                                <select size="1" id="rol_this_list_view" name="rol_this_list_view">
                                    <option value="0" ';
                                        if($role->getValue('rol_this_list_view') == 0)
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$gL10n->get('SYS_NOBODY').'</option>
                                    <option value="1" ';
                                        if($role->getValue('rol_this_list_view') == 1)
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$gL10n->get('ROL_ONLY_ROLE_MEMBERS').'</option>
                                    <option value="2" ';
                                        if($role->getValue('rol_this_list_view') == 2)
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$gL10n->get('ROL_ALL_MEMBERS').'</option>
                                </select>
                                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ROL_RIGHT_THIS_LIST_VIEW_DESC&amp;message_var1=ROL_RIGHT_ALL_LISTS_VIEW&amp;inline=true"><img 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ROL_RIGHT_THIS_LIST_VIEW_DESC&amp;message_var1=ROL_RIGHT_ALL_LISTS_VIEW\',this)" onmouseout="ajax_hideTooltip()"
                                    class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="rol_max_members">'.$gL10n->get('SYS_MAX_PARTICIPANTS').':</label></dt>
                            <dd>
                                <input type="text" id="rol_max_members" name="rol_max_members" size="3" maxlength="3" onchange="checkMaxMemberCount(this.value)" value="'.$role->getValue('rol_max_members').'" />&nbsp;('.$gL10n->get('ROL_WITHOUT_LEADER').')
                            </dd>
                        </dl>
                    </li>';
					// Beitragsverwaltung
					echo '
                    <li>
                        <dl>
                            <dt><label for="rol_cost">'.$gL10n->get('SYS_CONTRIBUTION').':</label></dt>
                            <dd>
                                <input type="text" id="rol_cost" name="rol_cost" size="6" maxlength="6" value="'. $role->getValue('rol_cost'). '" /> '.$gPreferences['system_currency'].'
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="rol_cost_period">'.$gL10n->get('SYS_CONTRIBUTION_PERIOD').':</label></dt>
                            <dd>
                                <select size="1" id="rol_cost_period" name="rol_cost_period">';
                                    // Zunaechst den unkonfigurierten Fall
                                    echo '<option value="0" ';
                                    if($role->getValue('rol_cost_period') == 0 || $role->getValue('rol_cost_period') == '')
                                    {
                                            echo ' selected="selected"';
                                    }
                                    echo '>--</option>';
                                    // Anschliessend alle moeglichen Werte die in der Klasse konfiguriert sind
                                    foreach ($role->getCostPeriode() as $role_cost_period) 
                                    {
                                        echo '<option value="'.$role_cost_period.'" ';
                                        if($role->getValue('rol_cost_period') == $role_cost_period)
                                        {
                                            echo 'selected="selected"';
                                        }
                                        echo '>'.TableRoles::getCostPeriodDesc($role_cost_period).'</option>';
                                    }
                                    echo '
                                </select>
                            </dd>
                        </dl>
                    </li>
                </ul>
            </div>
        </div>

        <div class="groupBox" id="admJustificationsBox">
            <div class="groupBoxHeadline">
                <a class="iconShowHide" href="javascript:showHideBlock(\'admJustificationsBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
                id="admJustificationsBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_AUTHORIZATION').'
            </div>

            <div class="groupBoxBody" id="admJustificationsBody">
                <ul class="formFieldList">
                    <li>
                        <div>
                            <input type="checkbox" id="rol_assign_roles" name="rol_assign_roles" ';
                            if($role->getValue('rol_assign_roles') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            if($role->getValue('rol_name') == $gL10n->get('SYS_WEBMASTER'))
                            {
                                echo ' disabled="disabled" ';
                            }
                            echo ' onchange="markRoleRight(\'rol_assign_roles\', \'rol_all_lists_view\', true)" value="1" />
                            <label for="rol_assign_roles"><img src="'. THEME_PATH. '/icons/roles.png" alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" /></label>&nbsp;
                            <label for="rol_assign_roles">'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'</label>
                            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ROL_RIGHT_ASSIGN_ROLES_DESC&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ROL_RIGHT_ASSIGN_ROLES_DESC\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>	                                
                        </div>
                    </li>
                    <li>
                        <div>
                            <input type="checkbox" id="rol_all_lists_view" name="rol_all_lists_view" ';
                            if($role->getValue('rol_all_lists_view') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            if($role->getValue('rol_name') == $gL10n->get('SYS_WEBMASTER'))
                            {
                                echo ' disabled="disabled" ';
                            }
                            echo ' onchange="markRoleRight(\'rol_all_lists_view\', \'rol_assign_roles\', false)" value="1" />
                            <label for="rol_all_lists_view"><img src="'. THEME_PATH. '/icons/lists.png" alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" /></label>&nbsp;
                            <label for="rol_all_lists_view">'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'</label>
                        </div>
                    </li>
                    <li>
                        <div>
                            <input type="checkbox" id="rol_approve_users" name="rol_approve_users" ';
                            if($role->getValue('rol_approve_users') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' value="1" />
                            <label for="rol_approve_users"><img src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" /></label>&nbsp;
                            <label for="rol_approve_users">'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'</label>
                        </div>
                    </li>
                    <li>
                        <div>
                            <input type="checkbox" id="rol_edit_user" name="rol_edit_user" ';
                            if($role->getValue('rol_edit_user') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' value="1" />
                            <label for="rol_edit_user"><img src="'. THEME_PATH. '/icons/group.png" alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" /></label>&nbsp;
                            <label for="rol_edit_user">'.$gL10n->get('ROL_RIGHT_EDIT_USER').'</label>
							<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ROL_RIGHT_EDIT_USER_DESC&amp;inline=true"><img 
				                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ROL_RIGHT_EDIT_USER_DESC\',this)" onmouseout="ajax_hideTooltip()"
				                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>	                                
                        </div>
                    </li>';
                    if($gPreferences['enable_mail_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_mail_to_all" name="rol_mail_to_all" ';
                                if($role->getValue('rol_mail_to_all') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_mail_to_all"><img src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" /></label>&nbsp;
                                <label for="rol_mail_to_all">'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'</label>
                            </div>
                        </li>';
                    }
                    echo '
                    <li>
                        <div>
                            <input type="checkbox" id="rol_profile" name="rol_profile" ';
                            if($role->getValue('rol_profile') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' value="1" />
                            <label for="rol_profile"><img src="'. THEME_PATH. '/icons/profile.png" alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" /></label>&nbsp;
                            <label for="rol_profile">'.$gL10n->get('ROL_RIGHT_PROFILE').'</label>
                        </div>
                    </li>';

                    if($gPreferences['enable_announcements_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_announcements" name="rol_announcements" ';
                                if($role->getValue('rol_announcements') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_announcements"><img src="'. THEME_PATH. '/icons/announcements.png" alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" /></label>&nbsp;
                                <label for="rol_announcements">'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'</label>
                            </div>
                        </li>';
                    }
                    if($gPreferences['enable_dates_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_dates" name="rol_dates" ';
                                if($role->getValue('rol_dates') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_dates"><img src="'. THEME_PATH. '/icons/dates.png" alt="'.$gL10n->get('ROL_RIGHT_DATES').'" /></label>&nbsp;
                                <label for="rol_dates">'.$gL10n->get('ROL_RIGHT_DATES').'</label>
                            </div>
                        </li>';
                    }
                    if($gPreferences['enable_photo_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_photo" name="rol_photo" ';
                                if($role->getValue('rol_photo') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_photo"><img src="'. THEME_PATH. '/icons/photo.png" alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" /></label>&nbsp;
                                <label for="rol_photo">'.$gL10n->get('ROL_RIGHT_PHOTO').'</label>
                            </div>
                        </li>';
                    }
                    if($gPreferences['enable_download_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_download" name="rol_download" ';
                                if($role->getValue('rol_download') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_download"><img src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" /></label>&nbsp;
                                <label for="rol_download">'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'</label>
                            </div>
                        </li>';
                    }
                    if($gPreferences['enable_guestbook_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_guestbook" name="rol_guestbook" ';
                                if($role->getValue('rol_guestbook') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_guestbook"><img src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" /></label>&nbsp;
                                <label for="rol_guestbook">'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'</label>
                            </div>
                        </li>';
                        // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                        if($gPreferences['enable_gbook_comments4all'] == false)
                        {
                            echo '
                            <li>
                                <div>
                                    <input type="checkbox" id="rol_guestbook_comments" name="rol_guestbook_comments" ';
                                    if($role->getValue('rol_guestbook_comments') == 1)
                                    {
                                        echo ' checked="checked" ';
                                    }
                                    echo ' value="1" />
                                    <label for="rol_guestbook_comments"><img src="'. THEME_PATH. '/icons/comments.png" alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" /></label>&nbsp;
                                    <label for="rol_guestbook_comments">'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'</label>
                                </div>
                            </li>';
                        }
                    }
                    if($gPreferences['enable_weblinks_module'] > 0)
                    {
                        echo '
                        <li>
                            <div>
                                <input type="checkbox" id="rol_weblinks" name="rol_weblinks" ';
                                if($role->getValue('rol_weblinks') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="rol_weblinks"><img src="'. THEME_PATH. '/icons/weblinks.png" alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" /></label>&nbsp;
                                <label for="rol_weblinks">'.$gL10n->get('ROL_RIGHT_WEBLINKS').'</label>
                            </div>
                        </li>';
                    }
                echo '</ul>
            </div>
        </div>

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
                                <select size="1" id="rol_weekday" name="rol_weekday">
                                <option value="0"';
                                if($role->getValue('rol_weekday') == 0)
                                {
                                    echo ' selected="selected" ';
                                }
                                echo '>--</option>';
                                
                                // Anschliessend alle moeglichen Werte die in der Klasse konfiguriert sind
                                foreach ($role->getWeekdays() as $role_weekday) 
                                {
                                    echo '<option value="'.$role_weekday.'" ';
                                    if($role->getValue('rol_weekday') == $role_weekday)
                                    {
                                        echo 'selected="selected"';
                                    }
                                    echo '>'.TableRoles::getWeekdayDesc($role_weekday).'</option>';
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

        if($role->getValue('rol_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gUserFields, $role->getValue('rol_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $role->getValue('rol_timestamp_create'));

                if($role->getValue('rol_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gUserFields, $role->getValue('rol_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $role->getValue('rol_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
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

?>
