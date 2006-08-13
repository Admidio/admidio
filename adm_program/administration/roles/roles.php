<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * inactive: 0 - (Default) alle aktiven Rollen anzeigen
 *           1 - alle inaktiven Rollen anzeigen
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

if(isset($_GET['inactive'])
&& $_GET['inactive'] == 1)
{
    $valid = 0;
}
else
{
    $valid = 1; 
}

// Alle Rollen auflisten, die der Webmaster sehen darf
$sql    = "SELECT * FROM ". TBL_ROLES. ", ". TBL_ROLE_CATEGORIES. "
            WHERE rol_org_shortname = '$g_organization' 
              AND rol_valid         = $valid
              AND rol_rlc_id        = rlc_id
            ORDER BY rlc_name, rol_name ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Rollenverwaltung</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <h1>Rollenverwaltung</h1>

        <p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"roles_new.php\"><img 
                src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Rolle anlegen\"></a>
                <a class=\"iconLink\" href=\"roles_new.php\">Rolle anlegen</a>
            </span>
            &nbsp;&nbsp;&nbsp;&nbsp;";
            if($valid == true)
            {
                $description_lnk = "Inaktive Rollen";
                $description_lst = "Aktive Rollen";
                $image       = "wand_gray.png";
            }
            else
            {
                $description_lnk = "Aktive Rollen";
                $description_lst = "Inaktive Rollen";
                $image       = "wand.png";
            }
            echo "<span class=\"iconLink\">
                <a class=\"iconLink\" href=\"roles.php?inactive=$valid\"><img 
                src=\"$g_root_path/adm_program/images/$image\" style=\"vertical-align: middle;\" border=\"0\" alt=\"$description_lnk\"></a>
                <a class=\"iconLink\" href=\"roles.php?inactive=$valid\">$description_lnk</a>
            </span>
        </p>

        <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
                <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;$description_lst</th>
                <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Kategorie</th>
                <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Berechtigungen</th>
                <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur f&uuml;r Moderatoren sichtbar\" title=\"Rolle nur f&uuml;r Moderatoren sichtbar\"></th>
                <th class=\"tableHeader\">Funktionen</th>
            </tr>";

            while($row = mysql_fetch_object($result))
            {
                echo "
                <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                    <td style=\"text-align: left;\">&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=$row->rol_id\">$row->rol_name</a></td>
                    <td style=\"text-align: left;\">&nbsp;$row->rlc_name</td>
                    <td style=\"text-align: left;\">";
                        if($row->rol_moderation == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/wand.png\" 
                            alt=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\" title=\"Moderation (Benutzer &amp; Rollen verwalten uvm.)\">";
                        }
                        if($row->rol_edit_user == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/user.png\" 
                            alt=\"Daten aller Benutzer bearbeiten\" title=\"Daten aller Benutzer bearbeiten\">";
                        }
                        if($row->rol_announcements == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/note.png\" 
                            alt=\"Ank&uuml;ndigungen anlegen und bearbeiten\" title=\"Ank&uuml;ndigungen anlegen und bearbeiten\">";
                        }
                        if($row->rol_dates == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/date.png\" 
                            alt=\"Termine anlegen und bearbeiten\" title=\"Termine anlegen und bearbeiten\">";
                        }
                        if($row->rol_photo == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/photo.png\" 
                            alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\">";
                        }
                        if($row->rol_download == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/folder_down.png\" 
                            alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\">";
                        }
                        if($row->rol_guestbook == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/comment.png\" 
                            alt=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\" title=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\">";
                        }
                        if($row->rol_guestbook_comments == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/comments.png\" 
                            alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" title=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\">";
                        }
                        if($row->rol_mail_logout == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail.png\" 
                            alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\">";
                        }
                        if($row->rol_mail_login == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail_key.png\" 
                            alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\">";
                        }
                        if($row->rol_weblinks == 1)
                        {
                            echo "&nbsp;<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/globe.png\" 
                            alt=\"Weblinks anlegen und bearbeiten\" title=\"Weblinks anlegen und bearbeiten\">";
                        }
                    echo "</td>
                    <td style=\"text-align: center;\">";
                        if($row->rol_locked == 1)
                        {
                            echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" 
                            alt=\"Rolle nur f&uuml;r Moderatoren sichtbar\" title=\"Rolle nur f&uuml;r Moderatoren sichtbar\">";
                        }
                    echo "</td>
                    <td style=\"text-align: center;\">
                        <a href=\"$g_root_path/adm_program/modules/lists/lists_show.php?type=address&amp;mode=html&amp;rol_id=$row->rol_id\"><img 
                        src=\"$g_root_path/adm_program/images/application_view_columns.png\" border=\"0\" alt=\"Mitglieder anzeigen\" title=\"Mitglieder anzeigen\"></a>&nbsp;";
                        
                        if($valid == true)
                        {
                            echo "<a href=\"#\"><img src=\"$g_root_path/adm_program/images/add.png\" border=\"0\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\" 
                                onclick=\"window.open('$g_root_path/adm_program/modules/lists/members.php?rol_id=$row->rol_id&amp;popup=1','Titel','width=570,height=550,left=310,top=100,scrollbars=yes,resizable=yes')\">
                            </a>";
                        }
                        else
                        {
                            echo "<a href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=5\"><img 
                                src=\"$g_root_path/adm_program/images/wand.png\" border=\"0\" alt=\"Rolle aktivieren\" title=\"Rolle aktivieren\"></a>";
                        }

                        if($row->rol_name == "Webmaster")
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">";
                        }
                        else
                        {
                            if($valid == true)
                            {
                                echo "<a href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=1\">
                                <img src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Rolle l&ouml;schen\" title=\"Rolle l&ouml;schen\"></a>";
                            }
                            else
                            {
                                $load_url = urlencode("$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=4&amp;inactive=1");
                                echo "
                                <a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_role&amp;err_text=$row->rol_name&amp;err_head=L&ouml;schen&amp;button=2&amp;url=$load_url\">
                                <img src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Rolle l&ouml;schen\" title=\"Rolle l&ouml;schen\"></a>";
                            }
                        }
                    echo "</td>
                </tr>";
            }
        echo "</table>
    </div>";
    require("../../../adm_config/body_bottom.php");
echo "</body></html>";
?>