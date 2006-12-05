<?php
/******************************************************************************
 * Verwaltung der aller Mitglieder in der Datenbank
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * members - 1 : (Default) Nur Mitglieder der Gliedgemeinschaft anzeigen
 *           0 : Mitglieder, Ehemalige, Mitglieder anderer Gliedgemeinschaften
 * letter: alle User deren Nachnamen mit dem Buchstaben beginnt, werden angezeigt
 *
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

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if(!editUser())
{
    $g_message->show("norights");
}

if(isset($_GET['members']) && is_numeric($_GET['members']))
{
    $members = $_GET['members'];
}
else
{
    $members = 1;
}

if(isset($_GET['letter']) && strlen($_GET["letter"]) > 2)
{
    $g_message->show("invalid");
}

$restrict = "";
$listname = "";
$i = 0;
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl($g_current_url);

if(array_key_exists("letter", $_GET))
{
    if($_GET["letter"] != "%")
    {
        $letter = $_GET["letter"]. "%";
    }
    else
    {
        $letter = "%";
    }
}
else
{
    $letter = "%";
}

// alle Mitglieder zur Auswahl selektieren
// unbestaetigte User werden dabei nicht angezeigt
if($members == true)
{
    $sql    = "SELECT DISTINCT usr_id, usr_last_name, usr_first_name, usr_email, usr_homepage,
                      usr_login_name, usr_last_change
                 FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE usr_last_name LIKE {0}
                  AND usr_valid = 1
                  AND mem_usr_id = usr_id
                  AND mem_rol_id = rol_id
                  AND mem_valid  = 1
                  AND rol_org_shortname = '$g_current_organization->shortname'
                  AND rol_valid  = 1
                ORDER BY usr_last_name, usr_first_name ";
}
else
{
    $sql    = "SELECT usr_id, usr_last_name, usr_first_name, usr_email, usr_homepage,
                      usr_login_name, usr_last_change
                 FROM ". TBL_USERS. "
                WHERE usr_last_name LIKE {0}
                  AND usr_valid = 1
                ORDER BY usr_last_name, usr_first_name ";
}
$sql    = prepareSQL($sql, array($letter));
$result_mgl = mysql_query($sql, $g_adm_con);
db_error($result_mgl);


// User zaehlen, die mind. einer Rolle zugeordnet sind
$sql    = "SELECT COUNT(*)
             FROM ". TBL_USERS. "
            WHERE usr_valid = 1 ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$row = mysql_fetch_array($result);
$count_mem_rol = $row[0];

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Benutzerverwaltung</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div align=\"center\">
        <h1>Benutzerverwaltung</h1>";

        echo "<p>
            <span class=\"iconLink\">
                <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Login\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\">Benutzer anlegen</a>
            </span>
            &nbsp;&nbsp;&nbsp;&nbsp;";

            if($count_mem_rol != mysql_num_rows($result_mgl) || $members == false)
            {
                // Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
                if($members == 1)
                {
                    echo "<span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/members.php?members=0&letter=". str_replace("%", "", $letter). "\"><img
                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/group.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Benutzer anzeigen\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/members.php?members=0&letter=". str_replace("%", "", $letter). "\">Alle Benutzer anzeigen</a>
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;";
                }
                else
                {
                    echo "<span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/members.php?members=1&letter=". str_replace("%", "", $letter). "\"><img
                         class=\"iconLink\" src=\"$g_root_path/adm_program/images/user.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Benutzer anzeigen\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/members.php?members=1&letter=". str_replace("%", "", $letter). "\">Nur Mitglieder anzeigen</a>
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;";
                }
            }
            echo "
            <span class=\"iconLink\">
                <a href=\"import.php\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/database_in.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Login\"></a>
                <a class=\"iconLink\" href=\"import.php\">Benutzer importieren</a>
            </span>
        </p>";

        if($members == 1)
        {
            echo "<p>Alle Mitglieder ";
        }
        else
        {
            echo "<p>Alle Benutzer (Mitglieder, Ehemalige) ";
        }

        if($letter != "%")
        {
            echo " mit Nachnamen ". str_replace("%", "*", $letter);
        }
        echo " werden angezeigt</p>";

        echo "<p>";

            // Leiste mit allen Buchstaben des Alphabets anzeigen

            if($letter == "%")
            {
                echo "<b>Alle</b>&nbsp;&nbsp;&nbsp;";
            }
            else
            {
                echo "<a href=\"members.php?letter=%\">Alle</a>&nbsp;&nbsp;&nbsp;";
            }

            $letter_menu = "A";
            for($i = 0; $i < 26;$i++)
            {
                // Anzahl Mitglieder zum entsprechenden Buchstaben ermitteln
                if($members == 1)
                {
                    $sql    = "SELECT COUNT(usr_id) FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                                WHERE rol_org_shortname = '$g_organization'
                                  AND rol_valid  = 1
                                  AND mem_rol_id = rol_id
                                  AND mem_usr_id = usr_id
                                  AND mem_valid  = 1
                                  AND usr_last_name LIKE '$letter_menu%'
                                  AND usr_valid  = 1 ";
                }
                else
                {
                    $sql    = "SELECT COUNT(usr_id) FROM ". TBL_USERS. "
                                WHERE usr_last_name LIKE '$letter_menu%'
                                  AND usr_valid  = 1 ";
                }
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                $row = mysql_fetch_array($result);

                if($letter_menu == substr($letter, 0, 1))
                {
                    echo "<b>$letter_menu</b>";
                }
                elseif($row[0] > 0)
                {
                    echo "<a href=\"members.php?members=$members&letter=$letter_menu\">$letter_menu</a>";
                }
                else
                {
                    echo $letter_menu;
                }

                echo "&nbsp;&nbsp;";
                // naechsten Buchstaben anwaehlen
                $letter_menu = strNextLetter($letter_menu);
            }
        echo "</p>";

        if(mysql_num_rows($result_mgl) > 0)
        {
            echo "<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
                <tr>
                    <th class=\"tableHeader\" align=\"right\">Nr.</th>
                    <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/user.png\" alt=\"Mitglied bei $g_current_organization->longname\" title=\"Mitglied bei $g_current_organization->longname\" border=\"0\"></th>
                    <th class=\"tableHeader\" align=\"left\">&nbsp;Name</th>
                    <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/mail.png\" alt=\"E-Mail\" title=\"E-Mail\"></th>
                    <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Homepage\" title=\"Homepage\"></th>
                    <th class=\"tableHeader\" align=\"left\">&nbsp;Benutzer</th>
                    <th class=\"tableHeader\" align=\"center\">&nbsp;Aktualisiert am</th>
                    <th class=\"tableHeader\" align=\"center\">Bearbeiten</th>
                </tr>";
                $i = 0;

                while($row = mysql_fetch_object($result_mgl))
                {
                    if($members == true)
                    {
                        $is_member = true;
                    }
                    else
                    {
                        $is_member = isMember($row->usr_id);
                    }

                    $i++;
                    echo "
                    <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                        <td align=\"right\">$i&nbsp;</td>
                        <td align=\"center\">";
                            if($is_member == true)
                            {
                                echo "<a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->usr_id\"><img
                                    src=\"$g_root_path/adm_program/images/user.png\" alt=\"Mitglied bei $g_current_organization->longname\"
                                    title=\"Mitglied bei $g_current_organization->longname\" border=\"0\"></a>";
                            }
                            else
                            {
                                echo "&nbsp;";
                            }
                        echo "</td>
                        <td align=\"left\">&nbsp;<a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->usr_id\">$row->usr_last_name,&nbsp;$row->usr_first_name</a></td>
                        <td align=\"center\">";
                            if(strlen($row->usr_email) > 0)
                            {
                                if($g_preferences['enable_mail_module'] != 1)
                                {
                                    $mail_link = "mailto:$row->usr_email";
                                }
                                else
                                {
                                    $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?usr_id=$row->usr_id";
                                }
                                echo "<a href=\"$mail_link\"><img src=\"$g_root_path/adm_program/images/mail.png\"
                                    alt=\"E-Mail an $row->usr_email schreiben\" title=\"E-Mail an $row->usr_email schreiben\" border=\"0\"></a>";
                            }
                        echo "</td>
                        <td align=\"center\">";
                            if(strlen($row->usr_homepage) > 0)
                            {
                                $row->usr_homepage = stripslashes($row->usr_homepage);
                                if(substr_count(strtolower($row->usr_homepage), "http://") == 0)
                                {
                                    $row->usr_homepage = "http://". $row->usr_homepage;
                                }
                                echo "<a href=\"$row->usr_homepage\" target=\"_blank\"><img
                                    src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Homepage\" title=\"Homepage\" border=\"0\"></a>";
                            }
                        echo "</td>
                        <td align=\"left\">&nbsp;$row->usr_login_name</td>
                        <td align=\"center\">&nbsp;". mysqldatetime("d.m.y h:i" , $row->usr_last_change). "</td>
                        <td align=\"center\">";
                            if($is_member == true)
                            {
                                if(hasRole("Webmaster")
                                && strlen($row->usr_login_name) > 0
                                && strlen($row->usr_email) > 0
                                && $g_preferences['enable_system_mails'] == 1)
                                {
                                    // Link um E-Mail mit neuem Passwort zu zuschicken
                                    // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
                                    echo "<a href=\"$g_root_path/adm_program/administration/members/members_function.php?user_id=$row->usr_id&mode=5\"><img
                                        src=\"$g_root_path/adm_program/images/key.png\" border=\"0\" alt=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"
                                        title=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"></a>&nbsp;";
                                }
                                else
                                {
                                    echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">&nbsp;";
                                }

                                // nur Mitglieder der eigenen Organisation editieren
                                echo "<a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$row->usr_id\"><img
                                    src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Benutzerdaten bearbeiten\" title=\"Benutzerdaten bearbeiten\"></a>&nbsp;";
                            }
                            else
                            {
                                echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">
                                <img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">&nbsp;";
                            }

                            // pruefen, ob der User noch in anderen Organisationen aktiv ist
                            $sql    = "SELECT COUNT(*)
                                         FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
                                        WHERE rol_org_shortname <> '$g_organization'
                                          AND rol_valid          = 1
                                          AND mem_rol_id         = rol_id
                                          AND mem_valid          = 1
                                          AND mem_usr_id         = $row->usr_id ";
                            $result      = mysql_query($sql, $g_adm_con);
                            db_error($result);
                            $row_count_2 = mysql_fetch_array($result);

                            if($row_count_2[0] > 0)
                            {
                                // Mitglieder nicht loeschen, wenn sie noch in anderen Organisationen aktiv sind
                                if($is_member == true)
                                {
                                    echo "<a href=\"$g_root_path/adm_program/administration/members/members_function.php?user_id=$row->usr_id&mode=6\"><img
                                        src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Benutzer entfernen\" title=\"Benutzer entfernen\"></a>";
                                }
                            }
                            else
                            {
                                // Mitglied kann aus der Datenbank geloescht oder zu einem Ehemaligen gemacht werden
                                echo "<a href=\"members_function.php?user_id=$row->usr_id&amp;mode=1\"><img
                                    src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Benutzer l&ouml;schen\" title=\"Benutzer l&ouml;schen\"></a>";
                            }
                        echo "</td>
                    </tr>";
                }
            echo "</table>";
        }
        else
        {
            echo "<p>Es wurde keine Daten gefunden !</p><br />";
        }
    echo "</div>";
    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>