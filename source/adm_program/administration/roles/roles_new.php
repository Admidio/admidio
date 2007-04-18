<?php
/******************************************************************************
 * Rollen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rol_id: ID der Rolle, die bearbeitet werden soll
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/role_class.php");
require("../../system/role_dependency_class.php");

// nur Moderatoren duerfen Rollen anlegen und verwalten
if(!isModerator())
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

$_SESSION['navigation']->addUrl($g_current_url);

// Rollenobjekt anlegen
$role = new Role($g_adm_con);

if($req_rol_id > 0)
{
    $role->getRole($req_rol_id);
    
    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue("rol_org_shortname") != $g_organization)
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
            $role->setValue($key, $value);
        }        
    }
    unset($_SESSION['roles_request']);
}

// Html-Kopf ausgeben
$g_layout['title']  = "Rolle";
$g_layout['header'] = "
    <script type=\"text/javascript\">
        function hinzufuegen()
        {
            NeuerEintrag = new Option(document.formRole.AllRoles.options[document.formRole.AllRoles.selectedIndex].text, document.formRole.AllRoles.options[document.formRole.AllRoles.selectedIndex].value, false, true);
            document.formRole.AllRoles.options[document.formRole.AllRoles.selectedIndex] = null;
            document.formRole.elements['ChildRoles[]'].options[document.formRole.elements['ChildRoles[]'].length] = NeuerEintrag;
        }

        function entfernen()
        {
            NeuerEintrag = new Option(document.formRole.elements['ChildRoles[]'].options[document.formRole.elements['ChildRoles[]'].selectedIndex].text, document.formRole.elements['ChildRoles[]'].options[document.formRole.elements['ChildRoles[]'].selectedIndex].value, false, true);
            document.formRole.elements['ChildRoles[]'].options[document.formRole.elements['ChildRoles[]'].selectedIndex] = null;
            document.formRole.AllRoles.options[document.formRole.AllRoles.length] = NeuerEintrag;
        }

        function absenden()
        {
            for (var i = 0; i < document.formRole.elements['ChildRoles[]'].options.length; i++)
            {
                document.formRole.elements['ChildRoles[]'].options[i].selected = true;
            }

            document.formRole.submit();
        }
    </script>";

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"roles_function.php?rol_id=$req_rol_id&amp;mode=2\" method=\"post\" name=\"formRole\">
    <div class=\"formHead\">";
        if($req_rol_id > 0)
        {
            echo "Rolle &auml;ndern";
        }
        else
        {
            echo "Rolle anlegen";
        }
    echo "</div>
    <div class=\"formBody\">
        <div>
            <div style=\"text-align: right; width: 28%; float: left;\">Name:&nbsp;</div>
            <div style=\"text-align: left;\">
                <input type=\"text\" id=\"rol_name\" name=\"rol_name\" ";
                // bei bestimmte Rollen darf der Name nicht geaendert werden
                if($role->getValue("rol_name") == "Webmaster")
                {
                    echo " class=\"readonly\" readonly ";
                }
                echo " style=\"width: 330px;\" maxlength=\"50\" value=\"". htmlspecialchars($role->getValue("rol_name"), ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:&nbsp;</div>
            <div style=\"text-align: left;\">
                <input type=\"text\" name=\"rol_description\" style=\"width: 330px;\" maxlength=\"255\" value=\"". htmlspecialchars($role->getValue("rol_description"), ENT_QUOTES). "\">
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">Kategorie:&nbsp;</div>
            <div style=\"text-align: left;\">
                <select size=\"1\" name=\"rol_cat_id\">";
                    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                             WHERE cat_org_id = $g_current_organization->id
                               AND cat_type   = 'ROL'
                             ORDER BY cat_name ASC ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);

                    while($row = mysql_fetch_object($result))
                    {
                        echo "<option value=\"$row->cat_id\"";
                            // Default-Eintrag setzen
                            if($role->getValue("rol_cat_id") == $row->cat_id
                            || ($role->getValue("rol_cat_id") == 0 && $row->cat_name == 'Allgemein'))
                            {
                                echo " selected ";
                            }
                        echo ">$row->cat_name</option>";
                    }
                echo "</select>
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 28%; float: left;\">
                <label for=\"locked\"><img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur f&uuml;r Moderatoren sichtbar\"></label>&nbsp;
            </div>
            <div style=\"text-align: left;\">
                <input type=\"checkbox\" id=\"rol_locked\" name=\"rol_locked\" ";
                    if($role->getValue("rol_locked") == 1)
                    {
                        echo " checked ";
                    }
                    echo " value=\"1\" />
                <label for=\"rol_locked\">Rolle nur f&uuml;r Moderatoren sichtbar&nbsp;</label>
                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_locked','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
            </div>
        </div>

        <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
            <div class=\"groupBoxHeadline\">Berechtigungen</div>

            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                    <input type=\"checkbox\" id=\"rol_moderation\" name=\"rol_moderation\" ";
                    if($role->getValue("rol_moderation") == 1)
                    {
                        echo " checked ";
                    }
                    if($role->getValue("rol_name") == "Webmaster")
                    {
                        echo " disabled ";
                    }
                    echo " value=\"1\" />&nbsp;
                    <label for=\"rol_moderation\"><img src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Moderation (Rollen verwalten und zuordnen uvm.)\"></label>
                </div>
                <div style=\"text-align: left;\">
                    <label for=\"rol_moderation\">Moderation (Rollen verwalten und zuordnen uvm.)&nbsp;</label>
                    <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_moderation','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                    <input type=\"checkbox\" id=\"rol_edit_user\" name=\"rol_edit_user\" ";
                    if($role->getValue("rol_edit_user") == 1)
                    {
                        echo " checked ";
                    }
                    echo " value=\"1\" />&nbsp;
                    <label for=\"rol_edit_user\"><img src=\"$g_root_path/adm_program/images/group.png\" alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\"></label>
                </div>
                <div style=\"text-align: left;\">
                    <label for=\"rol_edit_user\">Profildaten und Rollenzuordnungen aller Benutzer bearbeiten&nbsp;</label>
                    <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_benutzer','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                    <input type=\"checkbox\" id=\"rol_profile\" name=\"rol_profile\" ";
                    if($role->getValue("rol_profile") == 1)
                        echo " checked ";
                    echo " value=\"1\" />&nbsp;
                    <label for=\"rol_profile\"><img src=\"$g_root_path/adm_program/images/user.png\" alt=\"Eigenes Profil bearbeiten\"></label>
                </div>
                <div style=\"text-align: left;\">
                    <label for=\"rol_profile\">Eigenes Profil bearbeiten&nbsp;</label>
                </div>
            </div>";
            if($g_preferences['enable_announcements_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_announcements\" name=\"rol_announcements\" ";
                        if($role->getValue("rol_announcements") == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_announcements\"><img src=\"$g_root_path/adm_program/images/note.png\" alt=\"Ank&uuml;ndigungen anlegen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_announcements\">Ank&uuml;ndigungen anlegen und bearbeiten&nbsp;</label>
                    </div>
                </div>";
            }
            if($g_preferences['enable_dates_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_dates\" name=\"rol_dates\" ";
                        if($role->getValue("rol_dates") == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_dates\"><img src=\"$g_root_path/adm_program/images/date.png\" alt=\"Termine anlegen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_dates\">Termine anlegen und bearbeiten&nbsp;</label>
                    </div>
                </div>";
            }
            if($g_preferences['enable_photo_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_photo\" name=\"rol_photo\" ";
                        if($role->getValue("rol_photo") == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_photo\"><img src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Fotos hochladen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_photo\">Fotos hochladen und bearbeiten&nbsp;</label>
                    </div>
                </div>";
            }
            if($g_preferences['enable_download_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_download\" name=\"rol_download\" ";
                        if($role->getValue("rol_download") == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_download\"><img src=\"$g_root_path/adm_program/images/folder_down.png\" alt=\"Downloads hochladen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_download\">Downloads hochladen und bearbeiten&nbsp;</label>
                    </div>
                </div>";
            }
            if($g_preferences['enable_guestbook_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_guestbook\" name=\"rol_guestbook\" ";
                        if($role->getValue("rol_guestbook") == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_guestbook\"><img src=\"$g_root_path/adm_program/images/comment.png\" alt=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_guestbook\">G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen&nbsp;</label>
                    </div>
                </div>";
                // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                if($g_preferences['enable_gbook_comments4all'] == false)
                {
                    echo "<div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                            <input type=\"checkbox\" id=\"rol_guestbook_comments\" name=\"rol_guestbook_comments\" ";
                            if($role->getValue("rol_guestbook_comments") == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"rol_guestbook_comments\"><img src=\"$g_root_path/adm_program/images/comments.png\" alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\"></label>
                        </div>
                        <div style=\"text-align: left;\">
                            <label for=\"rol_guestbook_comments\">Kommentare zu G&auml;stebucheintr&auml;gen anlegen&nbsp;</label>
                        </div>
                    </div>";
                }
            }
            if($g_preferences['enable_mail_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_mail_logout\" name=\"rol_mail_logout\" ";
                        if($role->getValue("rol_mail_logout") == 1)
                        {
                            echo " checked ";
                        }
                        if($role->getValue("rol_name") == "Webmaster")
                        {
                            echo " disabled ";
                        }                                
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_mail_logout\"><img src=\"$g_root_path/adm_program/images/mail.png\" alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_mail_logout\">Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_logout','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_mail_login\" name=\"rol_mail_login\" ";
                        if($role->getValue("rol_mail_login") == 1)
                        {
                            echo " checked ";
                        }
                        if($role->getValue("rol_name") == "Webmaster")
                        {
                            echo " disabled ";
                        }                                
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_mail_login\"><img src=\"$g_root_path/adm_program/images/mail_key.png\" alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_mail_login\">Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_login','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>";
            }
            if($g_preferences['enable_weblinks_module'])
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 10%; float: left; padding-right: 7px;\">
                        <input type=\"checkbox\" id=\"rol_weblinks\" name=\"rol_weblinks\" ";
                        if($role->getValue("rol_weblinks") == 1)
                            echo " checked ";
                        echo " value=\"1\" />&nbsp;
                        <label for=\"rol_weblinks\"><img src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Weblinks anlegen und bearbeiten\"></label>
                    </div>
                    <div style=\"text-align: left;\">
                        <label for=\"rol_weblinks\">Weblinks anlegen und bearbeiten&nbsp;</label>
                    </div>
                </div>";
            }
        echo "</div>

        <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
            <div class=\"groupBoxHeadline\">Abh&auml;ngigkeiten&nbsp;&nbsp;(optional)</div>

            <div style=\"margin-top: 6px;\">
                <p>Ein Mitglied der nachfolgenden Rollen soll auch automatisch Mitglied in dieser Rolle sein!</p>
                <p>Beim Setzten dieser Abh&auml;ngigkeit werden auch bereits existierende Mitglieder der abh&auml;ngigen Rolle Mitglied in der aktuellen Rolle. Beim Entfernen einer Abh&auml;ngigkeit werden Mitgliedschaften nicht aufgehoben!<p>
                <div style=\"text-align: left; float: left; padding-right: 5%;\">";

                    // holt eine Liste der ausgewählten Rolen
                    $childRoles = RoleDependency::getChildRoles($g_adm_con,$req_rol_id);

                    // Alle Rollen auflisten, die der Webmaster sehen darf
                    $sql    = "SELECT * FROM ". TBL_ROLES. "
                        WHERE rol_org_shortname = '$g_organization'
                          AND rol_valid         = 1
                        ORDER BY rol_name ";
                    $allRoles = mysql_query($sql, $g_adm_con);
                    db_error($allRoles,__FILE__,__LINE__);

                    if($childRoles == -1)
                        $noChildRoles = true;
                    else
                        $noChildRoles = false;

                    $childRoleObjects = array();

                    echo "unabh&auml;ngig<br>
                    <select name=\"AllRoles\" size=\"8\" style=\"width: 200px;\">";
                        while($row = mysql_fetch_object($allRoles))
                        {
                            if(in_array($row->rol_id,$childRoles)  )
                                $childRoleObjects[] = $row;
                            elseif ($row->rol_id == $req_rol_id)
                                continue;
                            else
                            echo "<option value=\"$row->rol_id\">$row->rol_name</option>";
                        }
                    echo "</select>
                    <br>
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"javascript:hinzufuegen()\"><img
                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Feld hinzuf&uuml;gen\"></a>
                        <a class=\"iconLink\" href=\"javascript:hinzufuegen()\">Rolle hinzuf&uuml;gen</a>
                    </span>
                </div>
                <div>
                    abh&auml;ngig<br>
                    <select name=\"ChildRoles[]\" size=\"8\" multiple style=\"width: 200px;\">";
                        foreach ($childRoleObjects as $childRoleObject)
                        {
                            echo "<option value=\"$childRoleObject->rol_id\">$childRoleObject->rol_name</option>";
                        }
                    echo "</select>
                    <br>
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"javascript:entfernen()\"><img
                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/delete.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Feld hinzuf&uuml;gen\"></a>
                        <a class=\"iconLink\" href=\"javascript:entfernen()\">Rolle entfernen</a>
                    </span>
                </div>
            </div>
        </div>


        <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
            <div class=\"groupBoxHeadline\">Eigenschaften&nbsp;&nbsp;(optional)</div>

            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 33%; float: left;\">max. Teilnehmer:&nbsp;</div>
                <div style=\"text-align: left;\">
                    <input type=\"text\" name=\"rol_max_members\" size=\"3\" maxlength=\"3\" value=\"";
                    if($role->getValue("rol_max_members") > 0)
                    {
                        echo $role->getValue("rol_max_members");
                    }
                    echo "\">&nbsp;(ohne Leiter)</div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 33%; float: left;\">G&uuml;ltig von:&nbsp;</div>
                <div style=\"text-align: left;\">
                    <input type=\"text\" name=\"rol_start_date\" size=\"10\" maxlength=\"10\" value=\"". $role->getValue("rol_start_date"). "\">
                    bis
                    <input type=\"text\" name=\"rol_end_date\" size=\"10\" maxlength=\"10\" value=\"". $role->getValue("rol_end_date"). "\">&nbsp;(Datum)
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 33%; float: left;\">Uhrzeit:&nbsp;</div>
                <div style=\"text-align: left;\">
                    <input type=\"text\" name=\"rol_start_time\" size=\"5\" maxlength=\"5\" value=\"". $role->getValue("rol_start_time"). "\">
                    bis
                    <input type=\"text\" name=\"rol_end_time\" size=\"5\" maxlength=\"5\" value=\"". $role->getValue("rol_end_time"). "\">
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 33%; float: left;\">Wochentag:&nbsp;</div>
                <div style=\"text-align: left;\">
                    <select size=\"1\" name=\"rol_weekday\">
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
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 33%; float: left;\">Ort:&nbsp;</div>
                <div style=\"text-align: left;\">
                    <input type=\"text\" name=\"rol_location\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($role->getValue("rol_location"), ENT_QUOTES). "\"></div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 33%; float: left;\">Beitrag:&nbsp;</div>
                <div style=\"text-align: left;\">
                    <input type=\"text\" name=\"rol_cost\" size=\"6\" maxlength=\"6\" value=\"". $role->getValue("rol_cost"). "\"> &euro;</div>
            </div>
        </div>

        <div style=\"margin-top: 15px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
                <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"speichern\" type=\"button\" value=\"speichern\" onclick=\"absenden()\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                &nbsp;Speichern</button>
        </div>";
        if($req_rol_id > 0 && $role->getValue("rol_usr_id_change") > 0)
        {
            // Angabe ueber die letzten Aenderungen
            $user_change = new User($g_adm_con, $role->getValue("rol_usr_id_change"));

            echo "<div style=\"margin-top: 6px;\">
                <span style=\"font-size: 10pt\">
                    Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $role->getValue("rol_last_change")).
                    " durch $user_change->first_name $user_change->last_name
                </span>
            </div>";
        }
    echo "</div>
</form>

<script type=\"text/javascript\"><!--\n
    document.getElementById('name').focus();
\n--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>