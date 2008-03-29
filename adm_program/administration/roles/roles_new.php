<?php
/******************************************************************************
 * Rollen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * rol_id: ID der Rolle, die bearbeitet werden soll
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/role_class.php");
require("../../system/role_dependency_class.php");

// nur Moderatoren duerfen Rollen anlegen und verwalten
if(!$g_current_user->assignRoles())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_rol_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET["rol_id"]))
{
    if(is_numeric($_GET["rol_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_rol_id = $_GET["rol_id"];
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Rollenobjekt anlegen
$role = new Role($g_db);

if($req_rol_id > 0)
{
    $role->getRole($req_rol_id);
    
    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
    
    // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
    if($role->getValue("rol_name")    == "Webmaster" 
    && $g_current_user->isWebmaster() == false)
    {
        $g_message->show("norights");
    }
}
if(isset($_SESSION['roles_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['roles_request'] as $key => $value)
    {
        if(strpos($key, "rol_") == 0)
        {
            $role->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['roles_request']);
}
else
{
    // Datum formatieren
    $role->setValue("rol_start_date", mysqldate('d.m.y', $role->getValue("rol_start_date")));
    $role->setValue("rol_end_date", mysqldate('d.m.y', $role->getValue("rol_end_date")));
}

// Html-Kopf ausgeben
if($req_rol_id > 0)
{
	$g_layout['title'] = "Rolle ändern";
}
else
{
	$g_layout['title'] = "Rolle anlegen";
	$role->setValue("rol_this_list_view", "1");
	$role->setValue("rol_mail_login", "1");
}
$g_layout['header'] = "
	<script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/calendar/calendar-popup.js\"></script>
    <link rel=\"stylesheet\" href=\"".THEME_PATH. "/css/calendar.css\" type=\"text/css\" />
    <script type=\"text/javascript\"><!--
        function hinzufuegen()
        {
            var child_roles = document.getElementById('ChildRoles');
            var all_roles   = document.getElementById('AllRoles');
            
            NeuerEintrag = new Option(all_roles.options[all_roles.selectedIndex].text, all_roles.options[all_roles.selectedIndex].value, false, true);
            all_roles.options[all_roles.selectedIndex] = null;
            child_roles.options[child_roles.length] = NeuerEintrag;
        }

        function entfernen()
        {
            var child_roles = document.getElementById('ChildRoles');
            var all_roles   = document.getElementById('AllRoles');
            
            NeuerEintrag = new Option(child_roles.options[child_roles.selectedIndex].text, child_roles.options[child_roles.selectedIndex].value, false, true);
            child_roles.options[child_roles.selectedIndex] = null;
            all_roles.options[all_roles.length] = NeuerEintrag;
        }

        function absenden()
        {
            var child_roles = document.getElementById('ChildRoles');
            
            for (var i = 0; i < child_roles.options.length; i++)
            {
                child_roles.options[i].selected = true;
            }

            form.submit();
        }
		var cal18 = new CalendarPopup(\"calendardiv\");
		cal18.setCssPrefix(\"calendar\");
    --></script>";

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form id=\"formRole\" action=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$req_rol_id&amp;mode=2\" method=\"post\">
<div class=\"formLayout\" id=\"edit_roles_form\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"rol_name\">Name:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"rol_name\" name=\"rol_name\" ";
                        // bei bestimmte Rollen darf der Name nicht geaendert werden
                        if($role->getValue("rol_name") == "Webmaster")
                        {
                            echo " class=\"readonly\" readonly=\"readonly\" ";
                        }
                        echo " style=\"width: 320px;\" maxlength=\"50\" value=\"". $role->getValue("rol_name"). "\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"rol_description\">Beschreibung:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"rol_description\" name=\"rol_description\" style=\"width: 320px;\" maxlength=\"255\" value=\"". $role->getValue("rol_description"). "\" />
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"rol_cat_id\">Kategorie:</label></dt>
                    <dd>
                        <select size=\"1\" id=\"rol_cat_id\" name=\"rol_cat_id\">
                            <option value=\" \""; 
                                if($role->getValue("rol_cat_id") == 0) 
                                {
                                    echo " selected=\"selected\"";
                                }
                                echo ">- Bitte w&auml;hlen -</option>";

                            $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                                     WHERE cat_org_id = ". $g_current_organization->getValue("org_id"). "
                                       AND cat_type   = 'ROL'
                                     ORDER BY cat_sequence ASC ";
                            $result = $g_db->query($sql);

                            while($row = $g_db->fetch_object($result))
                            {
                                echo "<option value=\"$row->cat_id\"";
                                    if($role->getValue("rol_cat_id") == $row->cat_id)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                echo ">$row->cat_name</option>";
                            }
                        echo "</select>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <div class=\"groupBox\" id=\"properties_box\" style=\"width: 90%;\">
            <div class=\"groupBoxHeadline\" id=\"properties_head\">
                <a class=\"iconShowHide\" href=\"javascript:showHideBlock('properties_body','". THEME_PATH. "')\"><img 
                id=\"img_properties_body\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>Eigenschaften
            </div>

            <div class=\"groupBoxBody\" id=\"properties_body\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"rol_max_members\">max. Teilnehmer:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"rol_max_members\" name=\"rol_max_members\" size=\"3\" maxlength=\"3\" value=\"";
                                if($role->getValue("rol_max_members") > 0)
                                {
                                    echo $role->getValue("rol_max_members");
                                }
                                echo "\" />&nbsp;(ohne Leiter)
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"rol_cost\">Beitrag:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"rol_cost\" name=\"rol_cost\" size=\"6\" maxlength=\"6\" value=\"". $role->getValue("rol_cost"). "\" /> &euro;
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <div>
                            <input type=\"checkbox\" id=\"rol_this_list_view\" name=\"rol_this_list_view\" ";
                            if($role->getValue("rol_this_list_view") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"rol_this_list_view\"><img src=\"". THEME_PATH. "/icons/page_white_text.png\" alt=\"Angemeldete Benutzer können die Mitgliederliste der Rolle einsehen\" /></label>&nbsp;
                            <label for=\"rol_this_list_view\">Angemeldete Benutzer können die Mitgliederliste der Rolle einsehen</label>
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=role_show_list&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"
								onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=role_show_list',this);\" onmouseout=\"ajax_hideTooltip()\" />
                        </div>
                    </li>";

                    if($g_preferences['enable_mail_module'])
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_mail_logout\" name=\"rol_mail_logout\" ";
                                    if($role->getValue("rol_mail_logout") == 1)
                                    {
                                        echo " checked=\"checked\" ";
                                    }
                                    if($role->getValue("rol_name") == "Webmaster")
                                    {
                                        echo " disabled=\"disabled\" ";
                                    }                                
                                    echo " value=\"1\" />
                                <label for=\"rol_mail_logout\"><img src=\"". THEME_PATH. "/icons/email.png\" alt=\"Besucher (ausgeloggt) können E-Mails an diese Rolle schreiben\" /></label>&nbsp;
                                <label for=\"rol_mail_logout\">Besucher (ausgeloggt) können E-Mails an diese Rolle schreiben</label>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=role_mail_logout&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"  
                                    onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=role_mail_logout',this);\" onmouseout=\"ajax_hideTooltip()\" />
                            </div>
                        </li>
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_mail_login\" name=\"rol_mail_login\" ";
                                    if($role->getValue("rol_mail_login") == 1)
                                    {
                                        echo " checked=\"checked\" ";
                                    }
                                    if($role->getValue("rol_name") == "Webmaster")
                                    {
                                        echo " disabled=\"disabled\" ";
                                    }                                
                                    echo " value=\"1\" />
                                <label for=\"rol_mail_login\"><img src=\"". THEME_PATH. "/icons/email_key.png\" alt=\"Eingeloggte Benutzer können E-Mails an diese Rolle schreiben\" /></label>&nbsp;
                                <label for=\"rol_mail_login\">Eingeloggte Benutzer können E-Mails an diese Rolle schreiben</label>
                            </div>
                        </li>";
                    }
                echo "</ul>
            </div>
        </div>
        
        <div class=\"groupBox\" id=\"justifications_box\" style=\"width: 90%;\">
            <div class=\"groupBoxHeadline\">
                <a class=\"iconShowHide\" href=\"javascript:showHideBlock('justifications_body','". THEME_PATH. "')\"><img 
                id=\"img_justifications_body\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>Berechtigungen
            </div>

            <div class=\"groupBoxBody\" id=\"justifications_body\">
                <ul class=\"formFieldList\">
                    <li>
                        <div>
                            <input type=\"checkbox\" id=\"rol_assign_roles\" name=\"rol_assign_roles\" ";
                            if($role->getValue("rol_assign_roles") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            if($role->getValue("rol_name") == "Webmaster")
                            {
                                echo " disabled=\"disabled\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"rol_assign_roles\"><img src=\"". THEME_PATH. "/icons/wand.png\" alt=\"Rollen verwalten und zuordnen\" /></label>&nbsp;
                            <label for=\"rol_assign_roles\">Rollen verwalten und zuordnen</label>
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                            	onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_zuordnen&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\" 
								onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_zuordnen',this);\" onmouseout=\"ajax_hideTooltip()\" />
                        </div>
                    </li>
                    <li>
                        <div>
                            <input type=\"checkbox\" id=\"rol_approve_users\" name=\"rol_approve_users\" ";
                            if($role->getValue("rol_approve_users") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"rol_approve_users\"><img src=\"". THEME_PATH. "/icons/properties.png\" alt=\"Registrierungen verwalten und zuordnen\" /></label>&nbsp;
                            <label for=\"rol_approve_users\">Registrierungen verwalten und zuordnen</label>
                        </div>
                    </li>
                    <li>
                        <div>
                            <input type=\"checkbox\" id=\"rol_edit_user\" name=\"rol_edit_user\" ";
                            if($role->getValue("rol_edit_user") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"rol_edit_user\"><img src=\"". THEME_PATH. "/icons/group.png\" alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" /></label>&nbsp;
                            <label for=\"rol_edit_user\">Profildaten und Rollenzuordnungen aller Benutzer bearbeiten</label>
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                            	onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_benutzer&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"
								onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_benutzer',this);\" onmouseout=\"ajax_hideTooltip()\" />
                        </div>
                    </li>
                    <li>
                        <div>
                            <input type=\"checkbox\" id=\"rol_profile\" name=\"rol_profile\" ";
                            if($role->getValue("rol_profile") == 1)
                                echo " checked=\"checked\" ";
                            echo " value=\"1\" />
                            <label for=\"rol_profile\"><img src=\"". THEME_PATH. "/icons/user.png\" alt=\"Eigenes Profil bearbeiten\" /></label>&nbsp;
                            <label for=\"rol_profile\">Eigenes Profil bearbeiten</label>
                        </div>
                    </li>";
                    
                    if($g_preferences['enable_announcements_module'] > 0)
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_announcements\" name=\"rol_announcements\" ";
                                if($role->getValue("rol_announcements") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_announcements\"><img src=\"". THEME_PATH. "/icons/note.png\" alt=\"Ankündigungen anlegen und bearbeiten\" /></label>&nbsp;
                                <label for=\"rol_announcements\">Ankündigungen anlegen und bearbeiten&nbsp;</label>
                            </div>
                        </li>";
                    }
                    if($g_preferences['enable_dates_module'] > 0)
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_dates\" name=\"rol_dates\" ";
                                if($role->getValue("rol_dates") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_dates\"><img src=\"". THEME_PATH. "/icons/date.png\" alt=\"Termine anlegen und bearbeiten\" /></label>&nbsp;
                                <label for=\"rol_dates\">Termine anlegen und bearbeiten</label>
                            </div>
                        </li>";
                    }
                    if($g_preferences['enable_photo_module'] > 0)
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_photo\" name=\"rol_photo\" ";
                                if($role->getValue("rol_photo") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_photo\"><img src=\"". THEME_PATH. "/icons/photo.png\" alt=\"Fotos hochladen und bearbeiten\" /></label>&nbsp;
                                <label for=\"rol_photo\">Fotos hochladen und bearbeiten</label>
                            </div>
                        </li>";
                    }
                    if($g_preferences['enable_download_module'] > 0)
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_download\" name=\"rol_download\" ";
                                if($role->getValue("rol_download") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_download\"><img src=\"". THEME_PATH. "/icons/folder_down.png\" alt=\"Downloads hochladen und bearbeiten\" /></label>&nbsp;
                                <label for=\"rol_download\">Downloads hochladen und bearbeiten</label>
                            </div>
                        </li>";
                    }
                    if($g_preferences['enable_guestbook_module'] > 0)
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_guestbook\" name=\"rol_guestbook\" ";
                                if($role->getValue("rol_guestbook") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_guestbook\"><img src=\"". THEME_PATH. "/icons/comment.png\" alt=\"Gästebucheinträge bearbeiten und löschen\" /></label>&nbsp;
                                <label for=\"rol_guestbook\">Gästebucheinträge bearbeiten und löschen</label>
                            </div>
                        </li>";
                        // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                        if($g_preferences['enable_gbook_comments4all'] == false)
                        {
                            echo "
                            <li>
                                <div>
                                    <input type=\"checkbox\" id=\"rol_guestbook_comments\" name=\"rol_guestbook_comments\" ";
                                    if($role->getValue("rol_guestbook_comments") == 1)
                                        echo " checked=\"checked\" ";
                                    echo " value=\"1\" />
                                    <label for=\"rol_guestbook_comments\"><img src=\"". THEME_PATH. "/icons/comments.png\" alt=\"Kommentare zu Gästebucheinträgen anlegen\" /></label>&nbsp;
                                    <label for=\"rol_guestbook_comments\">Kommentare zu Gästebucheinträgen anlegen</label>
                                </div>
                            </li>";
                        }
                    }
                    if($g_preferences['enable_weblinks_module'] > 0)
                    {
                        echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_weblinks\" name=\"rol_weblinks\" ";
                                if($role->getValue("rol_weblinks") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_weblinks\"><img src=\"". THEME_PATH. "/icons/globe.png\" alt=\"Weblinks anlegen und bearbeiten\" /></label>&nbsp;
                                <label for=\"rol_weblinks\">Weblinks anlegen und bearbeiten</label>
                            </div>
                        </li>";
                    }
                    //Listenrechte Alle Listen einsehen
                    echo "
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"rol_all_lists_view\" name=\"rol_all_lists_view\" ";
                                if($role->getValue("rol_all_lists_view") == 1)
                                    echo " checked=\"checked\" ";
                                echo " value=\"1\" />
                                <label for=\"rol_all_lists_view\"><img src=\"". THEME_PATH. "/icons/pages_white_text.png\" alt=\"Mitgliederlisten aller Rollen einsehen\" /></label>&nbsp;
                                <label for=\"rol_all_lists_view\">Mitgliederlisten aller Rollen einsehen</label>
                            </div>
                        </li>";                 
                echo "</ul>
            </div>
        </div>

        <div class=\"groupBox\" id=\"dates_box\" style=\"width: 90%;\">
            <div class=\"groupBoxHeadline\" id=\"dates_head\">
                <a class=\"iconShowHide\" href=\"javascript:showHideBlock('dates_body','". THEME_PATH. "')\"><img 
                id=\"img_dates_body\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>Termine / Treffen&nbsp;&nbsp;(optional)
            </div>

            <div class=\"groupBoxBody\" id=\"dates_body\">        
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"rol_start_date\">Gültig von:</label></dt>
                            <dd>
								<input type=\"text\" id=\"rol_start_date\" name=\"rol_start_date\" size=\"10\" maxlength=\"10\" value=\"". $role->getValue("rol_start_date"). "\" />
								<img id=\"ico_cal_date_from\" src=\"". THEME_PATH. "/icons/date.png\" onclick=\"javascript:cal18.select(document.forms[0].rol_start_date,'ico_cal_date_from','dd.MM.yyyy','rol_start_date','rol_end_date');\"  style=\"vertical-align:middle; cursor:pointer;\" alt=\"Kalender anzeigen\" title=\"Kalender anzeigen\" />
                                <label for=\"rol_end_date\">bis</label>
								<input type=\"text\" id=\"rol_end_date\" name=\"rol_end_date\" size=\"10\" maxlength=\"10\" value=\"". $role->getValue("rol_end_date"). "\" />
								<img id=\"ico_cal_date_to\" src=\"". THEME_PATH. "/icons/date.png\" onclick=\"javascript:cal18.select(document.forms[0].rol_end_date,'ico_cal_date_to','dd.MM.yyyy','rol_start_date','rol_end_date');\"  style=\"vertical-align:middle; cursor:pointer;\" alt=\"Kalender anzeigen\" title=\"Kalender anzeigen\" />&nbsp;(Datum)
								<span id=\"calendardiv\" style=\"position: absolute; visibility: hidden; \"></span>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"rol_start_time\">Uhrzeit:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"rol_start_time\" name=\"rol_start_time\" size=\"5\" maxlength=\"5\" value=\"". $role->getValue("rol_start_time"). "\" />
                                <label for=\"rol_end_time\">bis</label>
                                <input type=\"text\" id=\"rol_end_time\" name=\"rol_end_time\" size=\"5\" maxlength=\"5\" value=\"". $role->getValue("rol_end_time"). "\" />
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"rol_weekday\">Wochentag:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"rol_weekday\" name=\"rol_weekday\">
                                <option value=\"0\"";
                                if($role->getValue("rol_weekday") == 0)
                                {
                                    echo " selected=\"selected\"";
                                }
                                echo ">&nbsp;</option>\n";
                                for($i = 1; $i < 8; $i++)
                                {
                                    echo "<option value=\"$i\"";
                                    if($role->getValue("rol_weekday") == $i)
                                    {
                                        echo " selected=\"selected\"";
                                    }
                                    echo ">". $arrDay[$i-1]. "</option>\n";
                                }
                                echo "</select>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"rol_location\">Ort:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"rol_location\" name=\"rol_location\" size=\"30\" maxlength=\"30\" value=\"". $role->getValue("rol_location"). "\" />
                            </dd>
                        </dl>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class=\"groupBox\" id=\"dependancies_box\" style=\"width: 90%;\">
            <div class=\"groupBoxHeadline\" id=\"dependancies_head\">
                <a class=\"iconShowHide\" href=\"javascript:showHideBlock('dependancies_body','". THEME_PATH. "')\"><img
                id=\"img_dependancies_body\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>Abh&auml;ngigkeiten&nbsp;&nbsp;(optional)
            </div>

            <div class=\"groupBoxBody\" id=\"dependancies_body\">  
                <div style=\"margin-top: 6px;\">
                    <p>Ein Mitglied der nachfolgenden Rollen soll auch automatisch Mitglied in dieser Rolle sein!</p>
                    <p>Beim Setzten dieser Abhängigkeit werden auch bereits existierende Mitglieder der abhängigen 
                    Rolle Mitglied in der aktuellen Rolle. Beim Entfernen einer Abhängigkeit werden Mitgliedschaften 
                    nicht aufgehoben!</p>
                    <div style=\"text-align: left; float: left; padding-right: 5%;\">";

                        // holt eine Liste der ausgewählten Rolen
                        $childRoles = RoleDependency::getChildRoles($g_db,$req_rol_id);

                        // Alle Rollen auflisten, die der Benutzer sehen darf
                        $sql = "SELECT * 
                                  FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                 WHERE rol_valid  = 1
                                   AND rol_cat_id = cat_id
                                   AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                                 ORDER BY rol_name ";
                        $allRoles = $g_db->query($sql);

                        if($childRoles == -1)
                            $noChildRoles = true;
                        else
                            $noChildRoles = false;

                        $childRoleObjects = array();

                        echo "<div>unabhängig</div>
                        <div>
                            <select id=\"AllRoles\" size=\"8\" style=\"width: 200px;\">";
                                while($row = $g_db->fetch_object($allRoles))
                                {
                                    if(in_array($row->rol_id,$childRoles)  )
                                        $childRoleObjects[] = $row;
                                    elseif ($row->rol_id == $req_rol_id)
                                        continue;
                                    else
                                    echo "<option value=\"$row->rol_id\">$row->rol_name</option>";
                                }
                            echo "</select>
                        </div>
                        <div>
                            <span class=\"iconTextLink\">
                                <a href=\"javascript:hinzufuegen()\"><img
                                src=\"". THEME_PATH. "/icons/add.png\" alt=\"Rolle hinzufügen\" /></a>
                                <a href=\"javascript:hinzufuegen()\">Rolle hinzufügen</a>
                            </span>
                        </div>
                    </div>
                    <div>
                        <div>abhängig</div>
                        <div>
                            <select id=\"ChildRoles\" name=\"ChildRoles[]\" size=\"8\" multiple style=\"width: 200px;\">";
                                foreach ($childRoleObjects as $childRoleObject)
                                {
                                    echo "<option value=\"$childRoleObject->rol_id\">$childRoleObject->rol_name</option>";
                                }
                            echo "</select>
                        </div>
                        <div>
                            <span class=\"iconTextLink\">
                                <a href=\"javascript:entfernen()\"><img
                                src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Rolle entfernen\" /></a>
                                <a href=\"javascript:entfernen()\">Rolle entfernen</a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>";

        if($req_rol_id > 0 && $role->getValue("rol_usr_id_change") > 0)
        {
            // Angabe ueber die letzten Aenderungen
            $user_change = new User($g_db, $role->getValue("rol_usr_id_change"));

            echo "<div class=\"editInformation\">
                Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $role->getValue("rol_last_change")).
                " durch ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname"). "
            </div>";
        }

        echo "
        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" onclick=\"absenden()\">
                <img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />
                &nbsp;Speichern</button>
        </div>    
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--\n
    document.getElementById('rol_name').focus();\n";
    // Bloecke anzeigen/verstecken
    if($req_rol_id > 0)
    {
        if(strlen($role->getValue("rol_start_date")) == 0 
        && strlen($role->getValue("rol_end_date")) == 0
        && strlen($role->getValue("rol_start_time")) == 0
        && strlen($role->getValue("rol_end_time")) == 0
        && $role->getValue("rol_weekday") == 0
        && strlen($role->getValue("rol_location")) == 0)
        {
            echo "showHideBlock('dates_body','". THEME_PATH. "');\n";
        }
        if(count($childRoles) == 0)
        {
            echo "showHideBlock('dependancies_body','". THEME_PATH. "');\n";
        }
    }
echo "\n--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>
