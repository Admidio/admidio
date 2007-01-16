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

// nur Moderatoren duerfen Rollen anlegen und verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["rol_id"]))
{
    if(is_numeric($_GET["rol_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $rol_id = $_GET["rol_id"];
}
else
{
    $rol_id = 0;
}

$_SESSION['navigation']->addUrl($g_current_url);

if ($rol_id > 0)
{
    // Daten der Rolle auslesen und gleichzeitig pruefen, 
    // ob die Rolle auch zur aktuellen Orga gehoert
    $sql    = "SELECT * FROM ". TBL_ROLES. " 
                WHERE rol_id = {0}
                  AND rol_org_shortname = '$g_organization' ";
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    
    if (mysql_num_rows($result) == 0)
    {
        $g_message->show("invalid");
    }
    $row_ar = mysql_fetch_object($result);
}

if(isset($_SESSION['roles_request']))
{
   $form_values = $_SESSION['roles_request'];
   unset($_SESSION['roles_request']);
}
else
{
    $form_values['name']          = "";
    $form_values['description']   = "";
    $form_values['category']      = "";
    $form_values['locked']        = 0;
    $form_values['moderation']    = 0;
    $form_values['users']         = 0;
    $form_values['announcements'] = 0;
    $form_values['dates']         = 0;
    $form_values['photos']        = 0;
    $form_values['downloads']     = 0;
    $form_values['guestbook']     = 0;
    $form_values['guestbook_comments'] = 0;
    $form_values['profile']       = 1;
    $form_values['mail_logout']   = 0;
    $form_values['mail_login']    = 0;
    $form_values['links']         = 0;
    $form_values['max_members']   = 0;
    $form_values['start_date']    = "";
    $form_values['end_date']      = "";
    $form_values['start_time']    = "";
    $form_values['end_time']      = "";
    $form_values['weekday']       = 0;
    $form_values['location']      = "";
    $form_values['cost']          = "";

    // Wenn eine Rollen-ID uebergeben wurde, soll die Rolle geaendert werden
    // -> Felder mit Daten der Rolle vorbelegen

    if ($rol_id > 0)
    {
        // Rolle Webmaster darf nur vom Webmaster selber erstellt oder gepflegt werden
        if($row_ar->rol_name == "Webmaster" && !hasRole("Webmaster"))
        {
            if($g_current_user->id != $row_ar->rol_usr_id)
            {
                $g_message->show("norights");
            }
        }

        $form_values['name']          = $row_ar->rol_name;
        $form_values['description']   = $row_ar->rol_description;
        $form_values['category']      = $row_ar->rol_cat_id;
        $form_values['locked']        = $row_ar->rol_locked;
        $form_values['moderation']    = $row_ar->rol_moderation;
        $form_values['users']         = $row_ar->rol_edit_user;
        $form_values['announcements'] = $row_ar->rol_announcements;
        $form_values['dates']         = $row_ar->rol_dates;
        $form_values['photos']        = $row_ar->rol_photo;
        $form_values['downloads']     = $row_ar->rol_download;
        $form_values['guestbook']     = $row_ar->rol_guestbook;
        $form_values['guestbook_comments'] = $row_ar->rol_guestbook_comments;
        $form_values['guestbook_comments'] = $row_ar->rol_guestbook_comments;
        $form_values['profile']       = $row_ar->rol_profile;
        $form_values['mail_logout']   = $row_ar->rol_mail_logout;
        $form_values['mail_login']    = $row_ar->rol_mail_login;
        $form_values['links']         = $row_ar->rol_weblinks;
        $form_values['max_members']   = $row_ar->rol_max_members;
        $form_values['start_date']    = mysqldate("d.m.y", $row_ar->rol_start_date);
        $form_values['end_date']      = mysqldate("d.m.y", $row_ar->rol_end_date);
        $form_values['start_time']    = mysqltime("h:i",   $row_ar->rol_start_time);
        $form_values['end_time']      = mysqltime("h:i",   $row_ar->rol_end_time);
        $form_values['weekday']       = $row_ar->rol_weekday;
        $form_values['location']      = $row_ar->rol_location;
        $form_values['cost']          = $row_ar->rol_cost;
    }
}

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org - Version: ". ADMIDIO_VERSION. " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Rolle</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    echo "
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

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"roles_function.php?rol_id=$rol_id&amp;mode=2\" method=\"post\" name=\"formRole\">
            <div class=\"formHead\">";
                if($rol_id > 0)
                {
                    echo strspace("Rolle &auml;ndern", 2);
                }
                else
                {
                    echo strspace("Rolle anlegen", 2);
                }
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 28%; float: left;\">Name:</div>
                    <div style=\"text-align: left; margin-left: 30%;\">
                        <input type=\"text\" id=\"name\" name=\"name\" ";
                        // bei bestimmte Rollen darf der Name nicht geaendert werden
                        if(strcmp($form_values['name'], "Webmaster") == 0)
                        {
                            echo " class=\"readonly\" readonly ";
                        }
                        echo " style=\"width: 330px;\" maxlength=\"50\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:</div>
                    <div style=\"text-align: left; margin-left: 30%;\">
                        <input type=\"text\" name=\"description\" style=\"width: 330px;\" maxlength=\"255\" value=\"". htmlspecialchars($form_values['description'], ENT_QUOTES). "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 28%; float: left;\">Kategorie:</div>
                    <div style=\"text-align: left; margin-left: 30%;\">
                        <select size=\"1\" name=\"category\">";
                            $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                                     WHERE cat_org_id = $g_current_organization->id
                                       AND cat_type   = 'ROL'
                                     ORDER BY cat_name ASC ";
                            $result = mysql_query($sql, $g_adm_con);
                            db_error($result);

                            while($row = mysql_fetch_object($result))
                            {
                                echo "<option value=\"$row->cat_id\"";
                                    if($form_values['category'] == $row->cat_id
                                    || ($form_values['category'] == 0 && $row->cat_name == 'Allgemein'))
                                        echo " selected ";
                                echo ">$row->cat_name</option>";
                            }
                        echo "</select>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 28%; float: left;\">
                        <label for=\"locked\"><img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur f&uuml;r Moderatoren sichtbar\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 30%;\">
                        <input type=\"checkbox\" id=\"locked\" name=\"locked\" ";
                            if(isset($form_values['locked']) && $form_values['locked'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        <label for=\"locked\">Rolle nur f&uuml;r Moderatoren sichtbar&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_locked','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>

                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
                    <div class=\"groupBoxHeadline\">Berechtigungen</div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"moderation\" name=\"moderation\" ";
                            if(isset($form_values['moderation']) && $form_values['moderation'] == 1)
                            {
                                echo " checked ";
                            }
                            if(strcmp($form_values['name'], "Webmaster") == 0)
                            {
                                echo " disabled ";
                            }
                            echo " value=\"1\" />&nbsp;
                            <label for=\"moderation\"><img src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Moderation (Rollen verwalten und zuordnen uvm.)\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"moderation\">Moderation (Rollen verwalten und zuordnen uvm.)&nbsp;</label>
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_moderation','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"users\" name=\"users\" ";
                            if(isset($form_values['users']) && $form_values['users'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />&nbsp;
                            <label for=\"users\"><img src=\"$g_root_path/adm_program/images/group.png\" alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"users\">Profildaten und Rollenzuordnungen aller Benutzer bearbeiten&nbsp;</label>
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_benutzer','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"profile\" name=\"profile\" ";
                            if(isset($form_values['profile']) && $form_values['profile'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"profile\"><img src=\"$g_root_path/adm_program/images/user.png\" alt=\"Eigenes Profil bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"profile\">Eigenes Profil bearbeiten&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"announcements\" name=\"announcements\" ";
                            if(isset($form_values['announcements']) && $form_values['announcements'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"announcements\"><img src=\"$g_root_path/adm_program/images/note.png\" alt=\"Ank&uuml;ndigungen anlegen und bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"announcements\">Ank&uuml;ndigungen anlegen und bearbeiten&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"dates\" name=\"dates\" ";
                            if(isset($form_values['dates']) && $form_values['dates'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"dates\"><img src=\"$g_root_path/adm_program/images/date.png\" alt=\"Termine anlegen und bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"dates\">Termine anlegen und bearbeiten&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"photos\" name=\"photos\" ";
                            if(isset($form_values['photos']) && $form_values['photos'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"photos\"><img src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Fotos hochladen und bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"photos\">Fotos hochladen und bearbeiten&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"downloads\" name=\"downloads\" ";
                            if(isset($form_values['downloads']) && $form_values['downloads'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"downloads\"><img src=\"$g_root_path/adm_program/images/folder_down.png\" alt=\"Downloads hochladen und bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"downloads\">Downloads hochladen und bearbeiten&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"guestbook\" name=\"guestbook\" ";
                            if(isset($form_values['guestbook']) && $form_values['guestbook'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"guestbook\"><img src=\"$g_root_path/adm_program/images/comment.png\" alt=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"guestbook\">G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"guestbook_comments\" name=\"guestbook_comments\" ";
                            if(isset($form_values['guestbook_comments']) && $form_values['guestbook_comments'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"guestbook_comments\"><img src=\"$g_root_path/adm_program/images/comments.png\" alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"guestbook_comments\">Kommentare zu G&auml;stebucheintr&auml;gen anlegen&nbsp;</label>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"mail_logout\" name=\"mail_logout\" ";
                            if(isset($form_values['mail_logout']) && $form_values['mail_logout'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"mail_logout\"><img src=\"$g_root_path/adm_program/images/mail.png\" alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"mail_logout\">Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_logout','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"mail_login\" name=\"mail_login\" ";
                            if(isset($form_values['mail_login']) && $form_values['mail_login'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"mail_login\"><img src=\"$g_root_path/adm_program/images/mail_key.png\" alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"mail_login\">Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben&nbsp;</label>
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_login','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 10%; float: left;\">
                            <input type=\"checkbox\" id=\"links\" name=\"links\" ";
                            if(isset($form_values['links']) && $form_values['links'] == 1)
                                echo " checked ";
                            echo " value=\"1\" />&nbsp;
                            <label for=\"links\"><img src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Weblinks anlegen und bearbeiten\"></label>
                        </div>
                        <div style=\"text-align: left; margin-left: 12%;\">
                            <label for=\"links\">Weblinks anlegen und bearbeiten&nbsp;</label>
                        </div>
                    </div>
                </div>

                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 90%;\">
                    <div class=\"groupBoxHeadline\">Abh&auml;ngigkeiten&nbsp;&nbsp;(optional)</div>

                    <div style=\"margin-top: 6px;\">
                        <p>Ein Mitglied der nachfolgenden Rollen soll auch automatisch Mitglied in dieser Rolle sein!</p>
                        <p>Beim Setzten dieser Abh&auml;ngigkeit werden auch bereits existierende Mitglieder der abh&auml;ngigen Rolle Mitglied in der aktuellen Rolle. Beim Entfernen einer Abh&auml;ngigkeit werden Mitgliedschaften nicht aufgehoben!<p>
                        <div style=\"text-align: left; float: left; margin-left: 5%; margin-right: 10%;\">";

                            // holt eine Liste der ausgewählten Rolen
                            $childRoles = RoleDependency::getChildRoles($g_adm_con,$rol_id);

                            // Alle Rollen auflisten, die der Webmaster sehen darf
                            $sql    = "SELECT * FROM ". TBL_ROLES. "
                                WHERE rol_org_shortname = '$g_organization'
                                  AND rol_valid         = 1
                                ORDER BY rol_name ";
                            $allRoles = mysql_query($sql, $g_adm_con);
                            db_error($allRoles);

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
                                    elseif ($row->rol_id == $rol_id)
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
                        <div style=\"text-align: right; width: 33%; float: left;\">max. Teilnehmer:</div>
                        <div style=\"text-align: left; margin-left: 35%;\">
                            <input type=\"text\" name=\"max_members\" size=\"3\" maxlength=\"3\" value=\"";
                            if($form_values['max_members'] > 0)
                            {
                                echo $form_values['max_members'];
                            }
                            echo "\">&nbsp;(ohne Leiter)</div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 33%; float: left;\">G&uuml;ltig von:</div>
                        <div style=\"text-align: left; margin-left: 35%;\">
                            <input type=\"text\" name=\"start_date\" size=\"10\" maxlength=\"10\" value=\"". $form_values['start_date']. "\">
                            bis
                            <input type=\"text\" name=\"end_date\" size=\"10\" maxlength=\"10\" value=\"". $form_values['end_date']. "\">&nbsp;(Datum)
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 33%; float: left;\">Uhrzeit:</div>
                        <div style=\"text-align: left; margin-left: 35%;\">
                            <input type=\"text\" name=\"start_time\" size=\"5\" maxlength=\"5\" value=\"". $form_values['start_time']. "\">
                            bis
                            <input type=\"text\" name=\"end_time\" size=\"5\" maxlength=\"5\" value=\"". $form_values['end_time']. "\">
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 33%; float: left;\">Wochentag:</div>
                        <div style=\"text-align: left; margin-left: 35%;\">
                            <select size=\"1\" name=\"weekday\">
                            <option value=\"0\"";
                            if($form_values['weekday'] == 0)
                            {
                                echo " selected=\"selected\"";
                            }
                            echo ">&nbsp;</option>\n";
                            for($i = 1; $i < 8; $i++)
                            {
                                echo "<option value=\"$i\"";
                                if($form_values['weekday'] == $i)
                                {
                                    echo " selected=\"selected\"";
                                }
                                echo ">". $arrDay[$i-1]. "</option>\n";
                            }
                            echo "</select>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 33%; float: left;\">Ort:</div>
                        <div style=\"text-align: left; margin-left: 35%;\">
                            <input type=\"text\" name=\"location\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($form_values['location'], ENT_QUOTES). "\"></div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 33%; float: left;\">Beitrag:</div>
                        <div style=\"text-align: left; margin-left: 35%;\">
                            <input type=\"text\" name=\"cost\" size=\"6\" maxlength=\"6\" value=\"". $form_values['cost']. "\"> &euro;</div>
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
                if($rol_id > 0 && $row_ar->rol_usr_id_change > 0)
                {
                    // Angabe ueber die letzten Aenderungen
                    $sql    = "SELECT usr_first_name, usr_last_name
                                 FROM ". TBL_USERS. "
                                WHERE usr_id = $row_ar->rol_usr_id_change ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                    $row = mysql_fetch_array($result);

                    echo "<div style=\"margin-top: 6px;\">
                        <span style=\"font-size: 10pt\">
                            Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $row_ar->rol_last_change).
                            " durch $row[0] $row[1]
                        </span>
                    </div>";
                }
            echo "</div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--\n
        document.getElementById('name').focus();
    \n--></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>