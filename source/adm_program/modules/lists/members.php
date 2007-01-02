<?php
/******************************************************************************
 * Funktionen zuordnen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * rol_id     - Rolle der Mitglieder hinzugefuegt oder entfernt werden sollen
 * restrict:    Begrenzte Userzahl:
 *              m - (Default) nur Mitglieder
 *              u - alle in der Datenbank gespeicherten user
 * popup   :    0 - (Default) Fenster wird normal mit Homepagerahmen angezeigt
 *              1 - Fenster wurde im Popupmodus aufgerufen
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

// Uebergabevariablen pruefen

if(isset($_GET["rol_id"]) && is_numeric($_GET["rol_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $role_id = $_GET["rol_id"];
}

if(isset($_GET["restrict"]) && $_GET["restrict"] != "m" && $_GET["restrict"] != "u")
{
    $g_message->show("invalid");
}

if(isset($_GET["popup"]))
{
    if(is_numeric($_GET["popup"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["popup"] = 0;
}

//Erfassen der uebergeben Rolle
$sql="  SELECT *
        FROM ". TBL_ROLES. "
        WHERE rol_id = {0}";
$sql    = prepareSQL($sql, array($role_id));
$result_role = mysql_query($sql, $g_adm_con);
db_error($result);
$role = mysql_fetch_object($result_role);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if((!isModerator() && !isGroupLeader($role_id) && !$g_current_user->editUser()) || (!hasRole("Webmaster") && $role->rol_name=="Webmaster") || $role->rol_org_shortname!=$g_organization)
{
    $g_message->show("norights");
}

//festlegen der Spaltenzahl er Tabelle
$column=6;

//uebername ob nur Mitglieder oder alle User der Datenbank angezeigt werden sollen
$restrict=$_GET["restrict"];
if(strlen($restrict) == 0 || !isModerator() || !$g_current_user->editUser())
{
    $restrict="m";
}

//Falls gefordert, nur Aufruf von Inhabern der Rolle Mitglied
if($restrict=="m")
{
    $sql = "SELECT DISTINCT usr_id, usr_last_name, usr_first_name, usr_birthday, usr_city, usr_phone, usr_address, usr_zip_code
            FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
            WHERE usr_id   = mem_usr_id
            AND rol_org_shortname = '$g_organization'
            AND mem_rol_id = rol_id
            AND mem_valid  = 1
            AND rol_valid  = 1
            AND usr_valid  = 1
            ORDER BY usr_last_name, usr_first_name ASC ";
    $result_user = mysql_query($sql, $g_adm_con);
    db_error($result_user);
    //Zaehlen wieviele Leute in der Datenbank stehen
    $user_anzahl = mysql_num_rows($result_user);
}

//Falls gefordert, aufrufen alle Leute aus der Datenbank
if($restrict=="u")
{
    $sql = "SELECT usr_id, usr_last_name, usr_first_name, usr_birthday, usr_city, usr_phone, usr_address, usr_zip_code
            FROM ". TBL_USERS. "
            WHERE usr_valid = 1
            ORDER BY usr_last_name, usr_first_name ASC ";
    $result_user = mysql_query($sql, $g_adm_con);
    db_error($result_user);
    //Zaehlen wieviele Leute in der Datenbank stehen
    $user_anzahl = mysql_num_rows($result_user);
}

//Erfassen welche Anfansgsbuchstaben bei Nachnamen Vorkommen
$first_letter_array = array();
for($x=0; $user = mysql_fetch_array($result_user); $x++)
{
    if(!in_array(ord($user['usr_last_name']), $first_letter_array))
    {
        $first_letter_array[$x]= ord($user['usr_last_name']);
    }
}
mysql_data_seek ($result_user, 0);

//Erfassen wer die Rolle bereits hat oder schon mal hatte
$sql="  SELECT mem_usr_id, mem_rol_id, mem_valid, mem_leader
        FROM ". TBL_MEMBERS. "
        WHERE mem_rol_id = {0}";
$sql    = prepareSQL($sql, array($role_id));
$result_role_member = mysql_query($sql, $g_adm_con);
db_error($result_role_member);

//Schreiben der User-IDs die die Rolle bereits haben oder hatten in Array
//Schreiben der Leiter der Rolle in weiters arry
$role_member = array();
$group_leaders = array();
for($y=0; $member = mysql_fetch_array($result_role_member); $y++)
{
    if($member['mem_valid']==1)
    {
        $role_member[$y]= $member['mem_usr_id'];
    }
    if($member["mem_leader"]==1)
    {
        $group_leaders[$y]= $member['mem_usr_id'];
    }
}

// User zaehlen, die mind. einer Rolle zugeordnet sind
$sql    = "SELECT COUNT(*)
             FROM ". TBL_USERS. "
            WHERE usr_valid = 1 ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$row = mysql_fetch_array($result);
$count_valid_users = $row[0];

//Beginn HTML
echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>Mitglieder zuordnen</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <script type=\"text/javascript\"><!--
        function markMember(element)
        {
            if(element.checked == true)
            {
                var name   = element.name;
                var pos_number = name.search('_') + 1;
                var number = name.substr(pos_number, name.length - pos_number);
                var role_name = 'member_' + number;
                document.getElementById(role_name).checked = true;
            }
        }

        function unmarkLeader(element)
        {
            if(element.checked == false)
            {
                var name   = element.name;
                var pos_number = name.search('_') + 1;
                var number = name.substr(pos_number, name.length - pos_number);
                var role_name = 'leader_' + number;
                document.getElementById(role_name).checked = false;
            }
        }
    --></script>

    <!--[if lt IE 7]>
        <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    if($_GET['popup'] == 0)
    {
        require("../../../adm_config/header.php");
    }
echo "</head>";

if($_GET['popup'] == 0)
{
    require("../../../adm_config/body_top.php");
}
else
{
    echo "<body>";
}

//Beginn Formular
echo"
<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
    <a name=\"Anfang\"></a>
    <form action=\"members_save.php?role_id=".$role_id. "&amp;popup=". $_GET['popup']. "&amp;url=$url\" method=\"post\" name=\"Mitglieder\">
       <h2>Mitglieder zu $role->rol_name zuordnen</h2>";

        if($count_valid_users != $user_anzahl || $restrict == "u")
        {
            //Button Alle bzw. nur Mitglieder anzeigen
            echo "<p>";
            if($restrict=="m" && (isModerator() || $g_current_user->editUser()))
            {
                echo "<span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=u\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/group.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Alle Benutzer anzeigen\"></a>
                    <a class=\"iconLink\" href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=u\">Alle Benutzer anzeigen</a>
                </span>";
            }
            else if($restrict=="u" && (isModerator() || $g_current_user->editUser()))
            {
                echo "<span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=m\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/user.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Nur Mitglieder anzeigen\"></a>
                    <a class=\"iconLink\" href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=m\">Nur Mitglieder anzeigen</a>
                </span>";
            }
            echo "</p>";
        }

        //Anfang Tabelle
        echo"
        <table class=\"tableList\" cellpadding=\"3\" cellspacing=\"0\" style=\"width: 95%;\">
            <tr>
                <th class=\"tableHeader\" style=\"text-align: center;\">Info</th>
                <th class=\"tableHeader\" style=\"text-align: center;\">Name</th>
                <th class=\"tableHeader\" style=\"text-align: center;\">Vorname</th>
                <th class=\"tableHeader\" style=\"text-align: center;\">Geburtsdatum</th>
                <th class=\"tableHeader\" style=\"text-align: center;\">Mitglied</th>
                <th class=\"tableHeader\" style=\"text-align: center;\">Leiter</th>
            </tr>";
            $letter_merker = "";

            //Ausgabe der Tabellenzeilen, ggf. einfuegen von Ankern
            while($user = mysql_fetch_array($result_user))
            {
                $letter = ord(strtoupper($user['usr_last_name']));
                if(strlen($letter_merker) > 0 && $letter < 65)
                {
                    // die ersten Ascii-Zeichen alle unter # anzeigen
                    $letter_merker = $letter;
                }

                //grosse Anfangsbuchstaben werden erst ab 50 Personen angezeigt
                if( $user_anzahl > 50
                && ($letter_merker != $letter || strlen($letter_merker) == 0))
                {
                    echo "<tr><td style=\"text-align: center;\" colspan=\"$column\">";

                    //Zahlen werden unter # zusammengefasst
                    if($letter < 65)
                    {
                        $letter_string = "#";
                        echo"<h2>$letter_string</h2>";
                    }
                    else if($letter>=65)
                    {
                        //Aktueller Anfangsbuchstabe plus Anker
                        $letter_string = chr($letter);
                        echo"<a name=\"$letter_string\"></a><h2>$letter_string</h2>";
                    }

                    //Buchstaben Links zu Ankern wenn mehr als 100 Namen angezeigt werden sollen
                    if($user_anzahl>100 && (($letter < 65 && $first_linkline!=true) || $letter>=65))
                    {
                        $first_linkline=true;
                        echo"<a href=\"#Anfang\">Anfang</a>&nbsp;";
                        for($menu_letter=65; $menu_letter<=90; $menu_letter++)
                        {
                            //Falls Aktueller Anfangsbuchstabe, Nur Buchstabe ausgeben
                            $menu_letter_string = chr($menu_letter);
                            if($letter==$menu_letter || !in_array($menu_letter, $first_letter_array))
                            {
                                echo"$menu_letter_string&nbsp;";
                            }
                            //Falls Nicht Link zu Anker
                            if($letter!=$menu_letter && in_array($menu_letter, $first_letter_array))
                            {
                                echo"<a href=\"#$menu_letter_string\">$menu_letter_string</a>&nbsp;";
                            }
                        }//for

                        echo"<a href=\"#Ende\">Ende</a>";
                    }// if User_anzahl>100

                    echo"</td></tr>";
                    $letter_merker = $letter;
                }

                //Ausgabe aller Personen mit entsprechendem Anfangsbuchstaben
                $user_text= $user['usr_first_name']."&nbsp;".$user['usr_last_name']."&nbsp;&nbsp;&nbsp;"
                            .$user['usr_address']."&nbsp;&nbsp;&nbsp;"
                            .$user['usr_plz']."&nbsp;".$user['usr_ort']."&nbsp;&nbsp;&nbsp;"
                            .$user['usr_tel1'];
                echo"
                <tr>
                    <td style=\"text-align: center;\">
                        <img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/note.png\" alt=\"Userinformationen\" title=\"$user_text\">
                    </td>
                    <td style=\"text-align: left;\">". $user['usr_last_name']."</td>
                    <td style=\"text-align: left;\">". $user['usr_first_name']."</td>

                    <td style=\"text-align: center;\">";
                        //Geburtstag nur ausgeben wenn bekannt
                        if($user['usr_birthday']!='0000-00-00')
                        {
                            echo mysqldate("d.m.y", $user['usr_birthday']);
                        }
                    echo"</td>

                    <td style=\"text-align: center;\">";
                        //Haekchen setzen ob jemand Mitglied ist oder nicht
                        if(in_array($user['usr_id'], $role_member))
                        {
                            echo"<input type=\"checkbox\" onclick=\"unmarkLeader(this)\" id=\"member_$user[0]\" name=\"member_$user[0]\" checked value=\"1\">";
                        }
                        else
                        {
                            echo"<input type=\"checkbox\" onclick=\"unmarkLeader(this)\" id=\"member_$user[0]\" name=\"member_$user[0]\" value=\"1\">";
                        }
                    echo"</td>

                    <td style=\"text-align: center;\">";
                        //Haekchen setzen ob jemand Leiter ist oder nicht
                        if(in_array($user['usr_id'], $group_leaders))
                        {
                            echo"<input type=\"checkbox\" onclick=\"markMember(this)\" id=\"leader_$user[0]\" name=\"leader_$user[0]\" checked value=\"1\">";
                        }
                        else
                        {
                            echo"<input type=\"checkbox\" onclick=\"markMember(this)\" id=\"leader_$user[0]\" name=\"leader_$user[0]\" value=\"1\">";
                        }
                    echo"</td>
                </tr>";
            }//Ende for-Schleife
        echo"</table>";

      //Buttons schliessen oder Speichern
        echo"<a name=\"Ende\"></a>
        <div style=\"margin: 8px;\">
            <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
                <img src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Schlie&szlig;en\">&nbsp;Schlie&szlig;en
            </button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">&nbsp;Speichern
            </button>
        </div>
   </form></div>";//Ende Formular

echo "</body>
</html>";
?>